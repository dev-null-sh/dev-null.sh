<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\DnsResolver;
use App\Models\Branch;
use Illuminate\Support\Str;

final readonly class BranchDnsVerification
{
    public function __construct(private DnsResolver $dns)
    {
        //
    }

    public function hostFor(string $domain): string
    {
        return '_devnull-branch.'.mb_strtolower(mb_rtrim($domain, '.'));
    }

    public function recordValue(Branch $branch): string
    {
        return 'dev-null-branch='.$branch->name.' token='.$branch->verification_token;
    }

    public function newToken(): string
    {
        return Str::lower(Str::random(32));
    }

    /**
     * Whether the branch's pending domain publishes a matching TXT record.
     */
    public function matches(Branch $branch): bool
    {
        return $this->status($branch)['matches'];
    }

    /**
     * Live TXT lookup for moderators reviewing a verification request.
     *
     * @return array{host: string|null, expected: string|null, records: list<string>, matches: bool}
     */
    public function status(Branch $branch): array
    {
        if ($branch->domain === null || $branch->verification_token === null) {
            return [
                'host' => null,
                'expected' => null,
                'records' => [],
                'matches' => false,
            ];
        }

        $host = $this->hostFor($branch->domain);
        $expected = $this->recordValue($branch);
        $records = $this->dns->txt($host);
        $normalizedExpected = mb_strtolower(preg_replace('/\s+/u', ' ', mb_trim($expected)) ?? '');

        $matches = false;
        foreach ($records as $txt) {
            if (mb_strtolower(preg_replace('/\s+/u', ' ', mb_trim($txt)) ?? '') === $normalizedExpected) {
                $matches = true;
                break;
            }
        }

        return [
            'host' => $host,
            'expected' => $expected,
            'records' => $records,
            'matches' => $matches,
        ];
    }

    public function isValidDomain(string $domain): bool
    {
        $domain = mb_strtolower(mb_rtrim(mb_trim($domain), '.'));

        if ($domain === '' || str_contains($domain, '/') || str_contains($domain, ' ')) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain);
    }

    public function normalizeDomain(string $domain): string
    {
        return mb_strtolower(mb_rtrim(mb_trim($domain), '.'));
    }
}
