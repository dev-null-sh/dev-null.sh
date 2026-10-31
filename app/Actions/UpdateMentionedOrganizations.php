<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Enums\ProfileType;
use App\Models\Organization;
use App\Models\Profile;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\RequestException;
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
        private GitHubProvider $github,
        private UpdateOrCreateOrganization $updateOrCreateOrganization,
        private DeleteOrganization $deleteOrganization,
    ) {
        //
    }

    /**
     * Asks GitHub about the lowercase handles that are neither a user's nor a known organization's, and refreshes the
     * known organizations among them once their profile is a day old. A handle that isn't an organization's isn't asked
     * about again for a day, and an organization GitHub no longer has is deleted, with the links to it. An
     * organization that keeps GitHub from showing it to the app, with an IP allow list for one, is skipped. Returns when
     * to try again if the rate limit cut the lookups short.
     *
     * @param  list<string>  $handles
     */
    public function handle(array $handles): ?CarbonImmutable
    {
        $profiles = Profile::query()->whereIn(DB::raw('LOWER(handle)'), $handles)->get();
        $known = Organization::query()->whereKey($profiles->where('type', ProfileType::Organization)->modelKeys())->get();

        /** @var list<array{Closure(): (array<array-key, mixed>|null), Closure(): mixed}> $lookups */
        $lookups = collect($handles)
            ->diff($profiles->map(fn (Profile $profile): string => Str::lower($profile->handle)))
            ->reject(fn (string $handle): bool => Cache::has("github-organizations:not-found:{$handle}"))
            ->map(fn (string $handle): array => [
                fn (): ?array => $this->github->accountByLogin($handle),
                fn (): bool => Cache::put("github-organizations:not-found:{$handle}", true, now()->addDays(self::NotFoundDays)),
            ])
            ->merge($known
                ->filter(fn (Organization $organization): bool => $organization->github_synced_at?->addHours(Organization::FRESH_FOR_HOURS)->isPast() ?? true)
                ->map(fn (Organization $organization): array => [
                    fn (): ?array => $this->github->account($organization->profile->github_id),
                    function () use ($organization): void {
                        $this->deleteOrganization->handle($organization);
                    },
                ]))
            ->take(self::MaxLookups)
            ->values()
            ->all();

        if ($lookups === []) {
            return null;
        }

        $pausedUntil = Cache::integer('github-organizations:paused', 0);

        if ($pausedUntil === 0) {
            $rateLimit = $this->github->rateLimit();

            if ($rateLimit !== null && $rateLimit['remaining'] < self::RateLimitFloor) {
                $pausedUntil = $rateLimit['resets_at']->getTimestamp();
                Cache::put('github-organizations:paused', $pausedUntil, $rateLimit['resets_at']);
            }
        }

        if ($pausedUntil > 0) {
            return CarbonImmutable::createFromTimestamp($pausedUntil);
        }

        foreach ($lookups as [$lookUp, $notFound]) {
            try {
                $account = $lookUp();
            } catch (RequestException $exception) {
                // The refusals an organization sets up: an IP allow list, SAML single sign-on or OAuth App access
                // restrictions. Any other one, a rate limit among them, is retried.
                $response = $exception->response;

                if (! $response->forbidden() || (! $response->hasHeader('X-GitHub-SSO') && ! Str::contains($response->fluent()->string('message')->value(), ['IP allow list', 'OAuth App access restrictions']))) {
                    throw $exception;
                }

                continue;
            }

            if (($account['type'] ?? null) !== 'Organization') {
                $notFound();

                continue;
            }

            $this->updateOrCreateOrganization->handle($account);
        }

        return null;
    }
}
