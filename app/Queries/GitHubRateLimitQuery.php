<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class GitHubRateLimitQuery
{
    public function __construct(private GitHubProvider $github)
    {
        //
    }

    /**
     * The limit the calls made as the OAuth app draw from, such as syncing SSH keys. Reading it does not count against
     * it. It is cached for a minute so polling does not call GitHub on every refresh, and a failed read is not cached,
     * so the next refresh tries again.
     *
     * @return array{remaining: int, limit: int, resets_at: string}|null
     */
    public function get(): ?array
    {
        /** @var array{remaining: int, limit: int, resets_at: string}|null */
        return Cache::remember('operations:github-rate-limit', 60, function (): ?array {
            try {
                $rateLimit = $this->github->rateLimit();
            } catch (HttpClientException) {
                return null;
            }

            return $rateLimit === null ? null : [...$rateLimit, 'resets_at' => $rateLimit['resets_at']->toIso8601String()];
        });
    }
}
