<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final readonly class GitHubRateLimitQuery
{
    /**
     * GitHub is called as the OAuth app, the same way SSH keys are synced, so this is the limit those calls draw from.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.github.client_id')) && filled(config('services.github.client_secret'));
    }

    /**
     * Reading the rate limit does not count against it. It is cached for a minute so polling does not call GitHub on
     * every refresh, and a failed read is not cached, so the next refresh tries again.
     *
     * @return array{remaining: int, limit: int, resets_at: string}|null
     */
    public function get(): ?array
    {
        /** @var array{remaining: int, limit: int, resets_at: string}|null */
        return Cache::remember('operations:github-rate-limit', 60, function (): ?array {
            try {
                $core = Http::baseUrl('https://api.github.com')
                    ->accept('application/vnd.github+json')
                    ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                    ->withBasicAuth(config()->string('services.github.client_id'), config()->string('services.github.client_secret'))
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get('rate_limit')
                    ->throw()
                    ->json('resources.core');
            } catch (HttpClientException) {
                return null;
            }

            if (! is_array($core) || ! is_int($core['remaining'] ?? null) || ! is_int($core['limit'] ?? null) || ! is_int($core['reset'] ?? null)) {
                return null;
            }

            return [
                'remaining' => $core['remaining'],
                'limit' => $core['limit'],
                'resets_at' => CarbonImmutable::createFromTimestamp($core['reset'])->toIso8601String(),
            ];
        });
    }
}
