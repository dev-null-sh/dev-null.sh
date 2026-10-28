<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProfileType;
use App\Models\Profile;
use App\Services\GitHub;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpdateMentionedOrganizations
{
    /**
     * GitHub is asked about this many handles at a time at most, so one commit or bio can't spend the shared rate
     * limit.
     */
    private const int MaxLookups = 5;

    /**
     * Below this many requests left, lookups wait for the rate limit to reset, leaving the rest to SSH key syncs.
     */
    private const int RateLimitFloor = 1000;

    private const int NotFoundDays = 1;

    public function __construct(
        private GitHub $github,
        private UpdateOrCreateOrganization $updateOrCreateOrganization,
        private DeleteOrganization $deleteOrganization,
    ) {
        //
    }

    /**
     * Asks GitHub about the lowercase handles that are neither a user's nor a known organization's, and refreshes the
     * known organizations among them once their profile is a day old. A handle that isn't an organization's isn't asked
     * about again for a day, and an organization GitHub no longer has is deleted, with the links to it. Returns when to
     * try again if the rate limit cut the lookups short.
     *
     * @param  list<string>  $handles
     */
    public function handle(array $handles): ?CarbonImmutable
    {
        $profiles = Profile::query()->whereIn(DB::raw('LOWER(handle)'), $handles)->get();
        $known = $profiles->where('type', ProfileType::Organization);

        /** @var list<array{string, Closure(): mixed}> $lookups */
        $lookups = collect($handles)
            ->diff($profiles->map(fn (Profile $profile): string => Str::lower($profile->handle)))
            ->reject(fn (string $handle): bool => Cache::has("github-organizations:not-found:{$handle}"))
            ->map(fn (string $handle): array => [
                "users/{$handle}",
                fn (): bool => Cache::put("github-organizations:not-found:{$handle}", true, now()->addDays(self::NotFoundDays)),
            ])
            ->merge($known
                ->filter(fn (Profile $organization): bool => $organization->github_synced_at?->addHours(Profile::FRESH_FOR_HOURS)->isPast() ?? true)
                ->map(fn (Profile $organization): array => [
                    "user/{$organization->github_id}",
                    function () use ($organization): void {
                        $this->deleteOrganization->handle($organization);
                    },
                ]))
            ->take(self::MaxLookups)
            ->values()
            ->all();

        $retryAt = null;

        foreach ($lookups as [$path, $notFound]) {
            $pausedUntil = Cache::integer('github-organizations:paused', 0);

            if ($pausedUntil > 0) {
                $retryAt = CarbonImmutable::createFromTimestamp($pausedUntil);

                break;
            }

            $response = $this->github->request()->get($path);
            $remaining = $response->header('X-RateLimit-Remaining');

            if ($remaining !== '' && (int) $remaining < self::RateLimitFloor) {
                $resetsAt = CarbonImmutable::createFromTimestamp((int) $response->header('X-RateLimit-Reset'));
                Cache::put('github-organizations:paused', $resetsAt->getTimestamp(), $resetsAt);
            }

            if ($response->notFound() || $response->throw()->json('type') !== 'Organization') {
                $notFound();

                continue;
            }

            /** @var array<array-key, mixed> $account */
            $account = $response->json();

            $this->updateOrCreateOrganization->handle($account);
        }

        return $retryAt;
    }
}
