<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\DnsResolver;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\IpUtils;
use Uri\WhatWg\Url;

/**
 * Fetches http(s) URLs only after every hop resolves to a public address, pinned so DNS rebinding cannot swap in a
 * private target. Bodies are cut off during transfer.
 */
final readonly class PublicHttpFetcher
{
    public const int MAX_REDIRECTS = 3;

    public const int MAX_BODY_BYTES = 1_048_576;

    /**
     * Non-public ranges that filter_var's private and reserved range flags let through.
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

    public function __construct(private DnsResolver $dns)
    {
        //
    }

    /**
     * Follows up to three redirects by hand so each hop is checked. Returns null when the URL must not be fetched or
     * the final response is not successful.
     *
     * @param  array<string, string>  $headers
     */
    public function get(string $url, string $accept = '*/*', array $headers = [], string $userAgent = 'devnull'): ?Response
    {
        $page = Url::parse($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $page instanceof Url; $hop++) {
            $address = $this->publicAddress($page);

            if ($address === null) {
                return null;
            }

            try {
                $response = $this->request($page, $address, $accept, $headers, $userAgent);
            } catch (ConnectionException) {
                return null;
            }

            if ($response->serverError() || $response->tooManyRequests()) {
                return null;
            }

            if (! $response->redirect()) {
                return $response->successful() ? $response : null;
            }

            $page = Url::parse($response->header('Location'), $page);
        }

        return null;
    }

    public function body(Response $response, int $maxBytes = self::MAX_BODY_BYTES): string
    {
        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        return Utils::copyToString($body, $maxBytes);
    }

    /**
     * Returns the address to connect to, or null when the URL must not be fetched.
     */
    public function publicAddress(Url $url): ?string
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
     * @param  array<string, string>  $headers
     */
    private function request(Url $url, string $address, string $accept, array $headers, string $userAgent): Response
    {
        $port = $url->getPort() ?? ($url->getScheme() === 'https' ? 443 : 80);

        $request = Http::withUserAgent(sprintf(
            'Mozilla/5.0 (compatible; %s/1.0; +%s)',
            $userAgent,
            config()->string('app.url'),
        ))
            ->accept($accept)
            ->withHeaders($headers)
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
}
