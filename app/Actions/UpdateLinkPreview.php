<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\LinkPreview;
use App\Services\PublicAddresses;
use Dom\HTMLDocument;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class UpdateLinkPreview
{
    private const int MAX_REDIRECTS = 3;

    private const int MAX_BODY_BYTES = 1_048_576;

    private const int MAX_IMAGE_URL_LENGTH = 2048;

    private const array NO_METADATA = [
        'title' => null,
        'description' => null,
        'image_url' => null,
        'site_name' => null,
    ];

    public function __construct(private PublicAddresses $publicAddresses)
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
            $address = $this->publicAddresses->of($page);

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
     * Connects to the address that was checked, see PublicAddresses::pin(). Bodies are cut off after 1 MB, and a body that was cut off
     * (by that limit or by the timeout) is still used, as metadata lives at the top of the page. English is asked for
     * explicitly, as sites such as YouTube otherwise pick the language from the server's location.
     */
    private function get(Url $url, string $address): Response
    {
        $request = Http::withUserAgent(sprintf('Mozilla/5.0 (compatible; devnull-linkpreview/1.0; +%s)', config()->string('app.url')))
            ->accept('text/html')
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->connectTimeout(3)
            ->timeout(5)
            ->withoutRedirecting()
            ->withOptions([
                'decode_content' => false,
                'progress' => fn (int $expected, int $downloaded): bool => $downloaded > self::MAX_BODY_BYTES,
                'curl' => $this->publicAddresses->pin($url, $address),
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
