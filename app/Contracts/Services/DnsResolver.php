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
     * Returns null when the lookup itself failed (transient DNS error), as opposed to an empty
     * list when the host was checked successfully and has no TXT records.
     *
     * @return list<string>|null
     */
    public function txt(string $host): ?array;
}
