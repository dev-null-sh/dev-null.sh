<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class GitHubLookupPauseQuery
{
    /**
     * Until when lookups wait, a timestamp. Named before developers were looked up too.
     */
    public const string CACHE_KEY = 'github-organizations:paused';

    /**
     * Below this many requests left, lookups wait for the rate limit to reset, leaving the rest to SSH key syncs.
     */
    private const int RateLimitFloor = 1000;

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    /**
     * Until when looking handles up on GitHub waits, or null when it can go on. Once the rate limit runs low, the pause
     * is remembered until it resets, so lookups stop asking how much is left.
     */
    public function get(): ?CarbonImmutable
    {
        $pausedUntil = Cache::integer(self::CACHE_KEY, 0);

        if ($pausedUntil === 0) {
            $rateLimit = $this->github->rateLimit();

            if ($rateLimit !== null && $rateLimit['remaining'] < self::RateLimitFloor) {
                $pausedUntil = $rateLimit['resets_at']->getTimestamp();
                Cache::put(self::CACHE_KEY, $pausedUntil, $rateLimit['resets_at']);
            }
        }

        return $pausedUntil > 0 ? CarbonImmutable::createFromTimestamp($pausedUntil) : null;
    }
}
