<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\DnsResolver;
use App\Models\LinkPreview;
use Dom\HTMLDocument;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Uri\WhatWg\Url;

final readonly class UpdateLinkPreview
{
    private const int MAX_REDIRECTS = 3;

    private const int MAX_BODY_BYTES = 1_048_576;

    private const int MAX_IMAGE_URL_LENGTH = 2048;

    /**
     * Non-public ranges that filter_var's private and reserved range flags let through: carrier-grade NAT, IETF protocol
     * assignments, 6to4 relays, benchmarking, documentation and multicast for IPv4; NAT64, discard, Teredo,
     * documentation, 6to4, site-local, multicast and IPv4-translated addresses for IPv6.
     *
     * @var list<string>
     */
    private const array BLOCKED_RANGES = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/32',
        '2001:db8::/32',
        '2002::/16',
        'fec0::/10',
        'ff00::/8',
        '::ffff:0:0:0/96',
    ];

    private const array NO_METADATA = [
        'title' => null,
        'description' => null,
        'image_url' => null,
        'site_name' => null,
    ];

    public function __construct(private DnsResolver $dns)
    {
        //
    }

    /**
     * Fetches the page behind the link and stores its Open Graph metadata. A page that can't or mustn't be fetched is
     * marked as fetched without metadata, so it is not tried again. Failures that may pass, such as connection errors,
     * timeouts, rate limits and server errors, are thrown instead, so the caller can try again later.
     */
    public function handle(LinkPreview $linkPreview): void
    {
        $linkPreview->update([...$this->fetchMetadata($linkPreview->url), 'fetched_at' => now()]);
    }

    /**
     * Follows up to three redirects by hand, so that every hop goes through the same checks as the original URL.
     *
     * @return array{title: string|null, description: string|null, image_url: string|null, site_name: string|null}
     */
    private function fetchMetadata(string $url): array
    {
        $page = Url::parse($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $page instanceof Url; $hop++) {
            $address = $this->publicAddress($page);

            if ($address === null) {
                return self::NO_METADATA;
            }

            $response = $this->get($page, $address);

            if ($response->serverError() || $response->tooManyRequests()) {
                $response->throw();
            }

            if (! $response->redirect()) {
                return $response->successful() && $this->isHtml($response)
                    ? $this->parse($this->body($response), $page)
                    : self::NO_METADATA;
            }

            $page = Url::parse($response->header('Location'), $page);
        }

        return self::NO_METADATA;
    }

    /**
     * Returns the address to connect to, or null when the URL must not be fetched: anything but plain http(s) on a
     * default port without credentials, or a host that resolves to nothing or to any non-public address.
     */
    private function publicAddress(Url $url): ?string
    {
        if (! in_array($url->getScheme(), ['http', 'https'], true)
            || ($url->getUsername() ?? '') !== ''
            || ($url->getPassword() ?? '') !== ''
            || ! in_array($url->getPort(), [null, 80, 443], true)) {
            return null;
        }

        $addresses = $this->dns->resolve(mb_trim((string) $url->getAsciiHost(), '[]'));

        return $addresses !== [] && array_all($addresses, $this->isPublic(...)) ? $addresses[0] : null;
    }

    private function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! IpUtils::checkIp($address, self::BLOCKED_RANGES);
    }

    /**
     * Connects to the address that was checked rather than resolving the host again, so DNS rebinding can't swap in a
     * private address. The empty host and port in CURLOPT_CONNECT_TO match every request, so the pin holds even if
     * cURL were to read the URL differently than we do. Bodies are cut off after 1 MB, and a body that was cut off
     * (by that limit or by the timeout) is still used, as metadata lives at the top of the page.
     */
    private function get(Url $url, string $address): Response
    {
        $port = $url->getPort() ?? ($url->getScheme() === 'https' ? 443 : 80);

        $request = Http::withUserAgent(sprintf('Mozilla/5.0 (compatible; devnull-linkpreview/1.0; +%s)', config()->string('app.url')))
            ->accept('text/html')
            ->connectTimeout(3)
            ->timeout(5)
            ->withoutRedirecting()
            ->withOptions([
                'decode_content' => false,
                'progress' => fn (int $expected, int $downloaded): bool => $downloaded > self::MAX_BODY_BYTES,
                'curl' => [
                    CURLOPT_CONNECT_TO => [sprintf('::%s:%d', str_contains($address, ':') ? "[{$address}]" : $address, $port)],
                ],
            ]);

        try {
            return $request->get($url->toAsciiString());
        } catch (ConnectionException $connectionException) {
            $cutOff = $connectionException->getPrevious();

            if ($cutOff instanceof ResponseException) {
                return new Response($cutOff->getResponse());
            }

            throw $connectionException;
        }
    }

    private function isHtml(Response $response): bool
    {
        $mediaType = mb_strtolower(mb_trim(Str::before($response->header('Content-Type'), ';')));

        return in_array($mediaType, ['text/html', 'application/xhtml+xml'], true);
    }

    private function body(Response $response): string
    {
        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        return Utils::copyToString($body, self::MAX_BODY_BYTES);
    }

    /**
     * @return array{title: string|null, description: string|null, image_url: string|null, site_name: string|null}
     */
    private function parse(string $html, Url $page): array
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $meta = [];

        foreach ($document->querySelectorAll('meta[content]') as $element) {
            $key = mb_strtolower(mb_trim($element->getAttribute('property') ?? $element->getAttribute('name') ?? ''));
            $meta[$key] ??= (string) $element->getAttribute('content');
        }

        return [
            'title' => $this->text([$meta['og:title'] ?? '', $meta['twitter:title'] ?? '', $document->title], 300),
            'description' => $this->text([$meta['og:description'] ?? '', $meta['twitter:description'] ?? '', $meta['description'] ?? ''], 500),
            'image_url' => $this->imageUrl([$meta['og:image:secure_url'] ?? '', $meta['og:image'] ?? '', $meta['twitter:image'] ?? ''], $page),
            'site_name' => $this->text([$meta['og:site_name'] ?? ''], 100),
        ];
    }

    /**
     * Returns the first candidate that still has text once entities are decoded and whitespace is collapsed,
     * truncated to the column's length.
     *
     * @param  list<string>  $candidates
     */
    private function text(array $candidates, int $limit): ?string
    {
        foreach ($candidates as $candidate) {
            $text = Str::squish(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($text !== '') {
                return mb_strlen($text) > $limit ? mb_rtrim(mb_substr($text, 0, $limit - 1)).'…' : $text;
            }
        }

        return null;
    }

    /**
     * Returns the first candidate that, resolved against the page URL, is an https URL.
     *
     * @param  list<string>  $candidates
     */
    private function imageUrl(array $candidates, Url $page): ?string
    {
        foreach ($candidates as $candidate) {
            $image = mb_trim($candidate) === '' ? null : Url::parse($candidate, $page);

            if ($image?->getScheme() === 'https' && mb_strlen($image->toAsciiString()) <= self::MAX_IMAGE_URL_LENGTH) {
                return $image->toAsciiString();
            }
        }

        return null;
    }
}
