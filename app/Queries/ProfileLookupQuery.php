<?php

declare(strict_types=1);

namespace App\Queries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @phpstan-type Lookup array{status: 'idle'|'pending'|'paused'|'missing', resumesAt: string|null}
 */
final readonly class ProfileLookupQuery
{
    /**
     * Where looking the handle up on GitHub stands, from the cache alone, so the page can poll it: never asked yet or
     * given up on, being looked up, waiting for the rate limit to reset, or no GitHub account. A handle GitHub turned
     * into a page isn't looked up anymore, so it has none.
     *
     * @return Lookup
     */
    public function get(string $handle): array
    {
        $handle = Str::lower($handle);
        $pausedUntil = Cache::integer(GitHubLookupPauseQuery::CACHE_KEY, 0);

        return [
            'status' => match (true) {
                Cache::has("github-profiles:not-found:{$handle}") => 'missing',
                $pausedUntil > 0 => 'paused',
                Cache::has("github-profiles:looking-up:{$handle}") => 'pending',
                default => 'idle',
            },
            'resumesAt' => $pausedUntil > 0 ? now()->setTimestamp($pausedUntil)->toIso8601String() : null,
        ];
    }
}
