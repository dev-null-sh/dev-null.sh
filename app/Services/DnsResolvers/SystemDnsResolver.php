<?php

declare(strict_types=1);

namespace App\Services\DnsResolvers;

use App\Contracts\Services\DnsResolver;
use Closure;

final readonly class SystemDnsResolver implements DnsResolver
{
    /**
     * @param  Closure(string, int): (array<array-key, mixed>|false)  $lookup
     */
    public function __construct(private Closure $lookup = dns_get_record(...))
    {
        //
    }

    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($this->isLocalhost($host)) {
            return ['127.0.0.1', '::1'];
        }

        $records = @($this->lookup)($host, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach (is_array($records) ? $records : [] as $record) {
            $address = is_array($record) ? $record['ip'] ?? $record['ipv6'] ?? null : null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /**
     * Names under "localhost" always mean this machine (RFC 6761), whatever a DNS server says about them.
     */
    private function isLocalhost(string $host): bool
    {
        $host = mb_strtolower(mb_rtrim($host, '.'));

        return $host === 'localhost' || str_ends_with($host, '.localhost');
    }
}
