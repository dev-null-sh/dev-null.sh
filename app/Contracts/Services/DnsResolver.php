<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface DnsResolver
{
    /**
     * Resolves a host name to its IPv4 and IPv6 addresses. An IP literal resolves to itself.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
