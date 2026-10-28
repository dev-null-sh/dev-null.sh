<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProfileType;
use App\Models\Commit;
use App\Models\Profile;
use App\Queries\MentionedOrganizationsQuery;
use App\Services\GitHub;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpdateCommitTopics
{
    /**
     * GitHub is asked about this many handles per commit at most, so one commit can't spend the shared rate limit.
     */
    private const int MaxLookups = 5;

    /**
     * Below this many requests left, lookups wait for the rate limit to reset, leaving the rest to SSH key syncs.
     */
    private const int RateLimitFloor = 1000;

    private const int NotFoundDays = 1;

    public function __construct(
        private GitHub $github,
        private MentionedOrganizationsQuery $mentionedOrganizations,
        private UpdateOrCreateOrganization $updateOrCreateOrganization,
        private DeleteOrganization $deleteOrganization,
        private CreateCommitTopics $createCommitTopics,
    ) {
        //
    }

    /**
     * Asks GitHub about the handles the commit mentions that are neither a user nor a known organization, and
     * refreshes the known organizations it mentions once their profile is a day old, then links the commit to them.
     * A handle that isn't an organization's isn't asked about again for a day, and an organization GitHub no longer
     * has is deleted, with the links to it. Returns when to try again if the rate limit cut the lookups short.
     */
    public function handle(Commit $commit): ?CarbonImmutable
    {
        $handles = $commit->mentionedNonUsers();

        if ($handles === []) {
            return null;
        }

        $known = Profile::query()
            ->where('type', ProfileType::Organization)
            ->whereIn(DB::raw('LOWER(handle)'), $handles)
            ->get();

        /** @var list<array{string, Closure(): mixed}> $lookups */
        $lookups = collect($handles)
            ->diff($known->map(fn (Profile $organization): string => Str::lower($organization->handle)))
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
            $pausedUntil = Cache::get('github-organizations:paused');

            if (is_int($pausedUntil)) {
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

        $this->createCommitTopics->handle($commit, $this->mentionedOrganizations->get($commit->body, $commit->mentions ?? []));

        return $retryAt;
    }
}
