<?php

declare(strict_types=1);

use App\Contracts\Services\DnsResolver;
use App\Services\LinkPreviews\LinkPreviewAddress;
use Uri\WhatWg\Url;

it('rejects non-public and NAT64 addresses', function (string $address): void {
    $validator = new LinkPreviewAddress(new readonly class($address) implements DnsResolver
    {
        public function __construct(private string $address)
        {
            //
        }

        public function resolve(string $host): array
        {
            return [$this->address];
        }
    });

    expect($validator->get(Url::parse('https://example.com/cat.gif')))->toBeNull();
})->with([
    'carrier-grade NAT' => '100.64.0.1',
    'IETF protocol assignment' => '192.0.0.1',
    'IPv4 documentation one' => '192.0.2.1',
    '6to4 relay' => '192.88.99.1',
    'benchmarking' => '198.18.0.1',
    'IPv4 documentation two' => '198.51.100.1',
    'IPv4 documentation three' => '203.0.113.1',
    'IPv4 multicast' => '224.0.0.1',
    'RFC 6052 loopback' => '64:ff9b::7f00:1',
    'local-use NAT64 loopback' => '64:ff9b:1::7f00:1',
    'broader NAT64 prefix one' => '64:ff9b:7f00:1::',
    'broader NAT64 prefix two' => '64:ff9b::1:7f00:1',
    'RFC 6052 private address' => '64:ff9b::a00:1',
    'discard-only' => '100::1',
    'Teredo' => '2001::1',
    'IPv6 documentation' => '2001:db8::1',
    'IPv6 6to4' => '2002::1',
    'site-local' => 'fec0::1',
    'IPv6 multicast' => 'ff00::1',
    'IPv4-compatible address' => '::2',
    'IPv4-mapped address' => '::ffff:127.0.0.1',
    'IPv4-translated address' => '::ffff:0:7f00:1',
    'IPv4 loopback' => '127.0.0.1',
    'IPv6 loopback' => '::1',
]);

it('rejects a host when any resolved address is not public', function (): void {
    $validator = new LinkPreviewAddress(new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34', '64:ff9b::7f00:1'];
        }
    });

    expect($validator->get(Url::parse('https://example.com/cat.gif')))->toBeNull();
});

it('accepts public addresses on default HTTP ports', function (string $url, string $address): void {
    $validator = new LinkPreviewAddress(new readonly class($address) implements DnsResolver
    {
        public function __construct(private string $address)
        {
            //
        }

        public function resolve(string $host): array
        {
            return [$this->address];
        }
    });

    expect($validator->get(Url::parse($url)))->toBe($address);
})->with([
    'IPv4 HTTPS default port' => ['https://example.com:443/cat.gif', '93.184.216.34'],
    'IPv6 HTTPS default port' => ['https://example.com:443/cat.gif', '2606:2800:220:1:248:1893:25c8:1946'],
    'IPv4 HTTP default port' => ['http://example.com:80/cat.gif', '93.184.216.34'],
]);

it('rejects non-default ports', function (): void {
    $validator = new LinkPreviewAddress(new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    expect($validator->get(Url::parse('https://example.com:8443/cat.gif')))->toBeNull();
});
