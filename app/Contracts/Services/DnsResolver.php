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

    /**
     * Looks up TXT records for a host name, returning each record's text value.
     *
     * @return list<string>
     */
    public function txt(string $host): array;
}
