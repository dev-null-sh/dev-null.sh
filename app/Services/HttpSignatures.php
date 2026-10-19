<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Uri\WhatWg\Url;

final readonly class HttpSignatures
{
    private const array SIGNED_HEADERS = ['(request-target)', 'host', 'date', 'digest'];

    /**
     * Mastodon accepts a signature dated up to 12 hours away from its own clock, so the same window is used here.
     */
    private const int MAX_CLOCK_SKEW_SECONDS = 43_200;

    /**
     * @return array<string, string>
     */
    public function sign(string $url, string $body, string $keyId, string $privateKey): array
    {
        return $this->signed('post', $url, ['Digest' => $this->digest($body)], $keyId, $privateKey);
    }

    /**
     * Servers in Mastodon's authorized fetch mode only answer a signed GET.
     *
     * @return array<string, string>
     */
    public function signFetch(string $url, string $keyId, string $privateKey): array
    {
        return $this->signed('get', $url, [], $keyId, $privateKey);
    }

    public function keyId(Request $request): ?string
    {
        return $this->parameters($request)['keyId'] ?? null;
    }

    public function verify(Request $request, string $publicKey): bool
    {
        $parameters = $this->parameters($request);
        $headers = explode(' ', mb_strtolower($parameters['headers'] ?? ''));
        $signature = base64_decode($parameters['signature'] ?? '', strict: true);

        if (array_diff(self::SIGNED_HEADERS, $headers) !== []
            || ! in_array($parameters['algorithm'] ?? 'rsa-sha256', ['rsa-sha256', 'hs2019'], true)
            || $signature === false
            || $request->header('Digest') !== $this->digest($request->getContent())
            || ! $this->isRecent($request->header('Date'))) {
            return false;
        }

        $signingString = implode("\n", array_map(fn (string $header): string => $header === '(request-target)'
            ? '(request-target): '.mb_strtolower($request->method()).' '.$request->getRequestUri()
            : $header.': '.$request->header($header), $headers));

        return openssl_verify($signingString, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function signed(string $method, string $url, array $headers, string $keyId, string $privateKey): array
    {
        $target = Url::parse($url);
        assert($target instanceof Url);

        $headers = [
            'Host' => $target->getAsciiHost().($target->getPort() !== null ? ':'.$target->getPort() : ''),
            'Date' => CarbonImmutable::now('UTC')->format('D, d M Y H:i:s \G\M\T'),
            ...$headers,
        ];

        $values = [
            '(request-target)' => $method.' '.$target->getPath().($target->getQuery() !== null ? '?'.$target->getQuery() : ''),
            ...array_change_key_case($headers),
        ];

        $signingString = implode("\n", array_map(fn (string $name, string $value): string => $name.': '.$value, array_keys($values), $values));

        openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        assert(is_string($signature));

        return [
            ...$headers,
            'Signature' => sprintf(
                'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
                $keyId,
                implode(' ', array_keys($values)),
                base64_encode($signature),
            ),
        ];
    }

    private function digest(string $body): string
    {
        return 'SHA-256='.base64_encode(hash('sha256', $body, binary: true));
    }

    private function isRecent(?string $date): bool
    {
        $sentAt = $date === null ? false : CarbonImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $date, 'UTC');

        return $sentAt instanceof CarbonImmutable && abs(now()->diffInSeconds($sentAt)) <= self::MAX_CLOCK_SKEW_SECONDS;
    }

    /**
     * @return array<string, string>
     */
    private function parameters(Request $request): array
    {
        preg_match_all('/(\w+)="([^"]*)"/', (string) $request->header('Signature'), $matches, PREG_SET_ORDER);

        return array_column($matches, 2, 1);
    }
}
