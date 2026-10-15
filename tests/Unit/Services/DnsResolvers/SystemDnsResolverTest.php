<?php

declare(strict_types=1);

use App\Contracts\Services\DnsResolver;
use App\Services\DnsResolvers\SystemDnsResolver;

it('is the default dns resolver', function (): void {
    expect(resolve(DnsResolver::class))->toBeInstanceOf(SystemDnsResolver::class);
});

it('resolves ip literals to themselves without a lookup', function (string $ip): void {
    $resolver = new SystemDnsResolver(fn (): never => throw new RuntimeException('No lookup expected.'));

    expect($resolver->resolve($ip))->toBe([$ip]);
})->with([
    '93.184.216.34',
    '127.0.0.1',
    '2606:2800:220:1:248:1893:25c8:1946',
    '::1',
]);

it('resolves localhost to loopback addresses without a lookup', function (string $host): void {
    $resolver = new SystemDnsResolver(fn (): never => throw new RuntimeException('No lookup expected.'));

    expect($resolver->resolve($host))->toBe(['127.0.0.1', '::1']);
})->with([
    'localhost',
    'LOCALHOST',
    'localhost.',
    'app.localhost',
]);

it('resolves ip literals and localhost with the default lookup', function (): void {
    $resolver = resolve(DnsResolver::class);

    expect($resolver->resolve('10.0.0.1'))->toBe(['10.0.0.1'])
        ->and($resolver->resolve('localhost'))->toBe(['127.0.0.1', '::1']);
});

it('returns the ipv4 and ipv6 addresses of a host', function (): void {
    $resolver = new SystemDnsResolver(function (string $host, int $type): array {
        expect($host)->toBe('example.com')
            ->and($type)->toBe(DNS_A | DNS_AAAA);

        return [
            ['host' => 'example.com', 'class' => 'IN', 'ttl' => 60, 'type' => 'A', 'ip' => '93.184.216.34'],
            ['host' => 'example.com', 'class' => 'IN', 'ttl' => 60, 'type' => 'CNAME', 'target' => 'example.net'],
            ['host' => 'example.com', 'class' => 'IN', 'ttl' => 60, 'type' => 'AAAA', 'ipv6' => '2606:2800:220:1:248:1893:25c8:1946'],
            'not a record',
        ];
    });

    expect($resolver->resolve('example.com'))->toBe(['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']);
});

it('returns no addresses when the lookup fails', function (): void {
    $resolver = new SystemDnsResolver(fn (): false => false);

    expect($resolver->resolve('example.com'))->toBe([]);
});

it('returns no addresses when the lookup warns', function (): void {
    $resolver = new SystemDnsResolver(function (): false {
        trigger_error('DNS Query failed', E_USER_WARNING);

        return false;
    });

    expect($resolver->resolve('example.com'))->toBe([]);
});
