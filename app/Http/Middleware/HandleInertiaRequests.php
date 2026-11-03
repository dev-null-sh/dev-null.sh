<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Dto\ReaderClock;
use App\Enums\Feature;
use App\Enums\Log;
use App\Enums\RepliesPerPage;
use App\Enums\UserUpdate;
use App\Http\Requests\ListNotificationsRequest;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;
use App\Queries\BioProfilesQuery;
use App\Queries\ConversationsQuery;
use App\Queries\DevelopersCountQuery;
use App\Queries\HotHeadlinesQuery;
use App\Queries\LiveFollowingQuery;
use App\Queries\NotificationGroupsQuery;
use App\Queries\SuggestedUsersQuery;
use App\Services\CommitTranslations;
use App\Services\Features;
use Closure;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\LiveProp;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * A deploy sends a page built for the previous version back for a full reload. That page already took the flashed
     * data off the session, so it is flashed again for the reload: the composer learns its commit went through.
     */
    public function onVersionChange(Request $request, Response $response): Response
    {
        /** @var array{flash?: array<string, mixed>} $page */
        $page = $response instanceof JsonResponse ? $response->getData(true) : [];

        $response = parent::onVersionChange($request, $response);

        if (($page['flash'] ?? []) !== []) {
            Inertia::flash($page['flash']);
        }

        return $response;
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $isAdmin = $request->user()?->isAdmin() ?? false;
        $user = $request->user();
        $showsWhoToFollow = $user instanceof User && $request->cookie('who_to_follow') !== 'false';
        $context = match (true) {
            $request->routeIs('user.show') => $request->route('user'),
            $request->routeIs('commit.show', 'commit-photo.show') => $request->route('commit'),
            default => null,
        };
        $context = $context instanceof User || $context instanceof Commit ? $context : null;

        $hasAside = ! $request->routeIs('onboarding.show') && ! $request->is('operations', 'operations/*');

        $suggestedUsers = $user instanceof User && $showsWhoToFollow && $hasAside
            ? Inertia::defer(fn (): Collection => resolve(SuggestedUsersQuery::class)->get(
                $user,
                round: max(0, (int) $request->cookie('who_to_follow_round')),
                spares: 3,
                context: $context,
            ))
            : null;

        // Counts the user's private updates refresh, wherever they were changed from. Guests have no channel.
        $live = fn (Closure $value, UserUpdate ...$updates): Closure|LiveProp => $user instanceof User
            ? Inertia::live($value, on: array_values(array_map(fn (UserUpdate $update): string => $update->value, $updates)), channel: new PrivateChannel($user))
            : $value;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'repository' => config('services.github.repository'),
            'auth' => [
                // What the user saved anywhere, from their profile to their preferences, shows on every page they have open.
                'user' => $live(fn (): ?User => $request->user()?->loadCounts(), UserUpdate::Account),
                'bioProfiles' => $live(fn (): array => $user instanceof User ? resolve(BioProfilesQuery::class)->get($user) : [], UserUpdate::Account),
                // The organizations the user is a member of, shown like the commits they author: theirs too, and who they
                // can post as once the feature is on.
                'organizations' => $live(fn (): array => $user instanceof User ? $user->organizations()->get()
                    ->map(fn (Organization $organization): array => ['type' => 'organization', ...$organization->toArray()])
                    ->all() : [], UserUpdate::Account),
                // Guests post nothing, so only members follow the feature being switched.
                'canPostAsOrganization' => $user instanceof User
                    ? Inertia::live(fn (): bool => resolve(Features::class)->enabled(Feature::OrganizationCommits), 'features', new Channel('features'))
                    : false,
                'isAdmin' => $isAdmin,
                'automaticTranslation' => $live(fn (): bool => $user->automatic_translation ?? true, UserUpdate::Account),
                // The language translations are read in, saved or from the browser, so a change saved elsewhere reaches
                // the translations already shown.
                'translationLanguage' => $live(fn (): string => resolve(CommitTranslations::class)->language(), UserUpdate::Account),
                'mustReadCommitRules' => $live(fn (): bool => $user instanceof User && $user->commit_rules_accepted_at === null, UserUpdate::Account),
                'termsToAccept' => $live(fn (): ?string => $user instanceof User && ! $user->hasAcceptedCurrentTerms() ? config()->string('legal.terms_version') : null, UserUpdate::Account),
                'log' => $live(fn (): ?Log => $request->user()?->log, UserUpdate::Account),
                // Each browser filters notifications its own way, and reloading the count goes through its cookie.
                'unreadNotificationsCount' => $live(fn (): int => $user instanceof User ? resolve(NotificationGroupsQuery::class)->unreadCount($user, ListNotificationsRequest::rememberedType($request, $user)) : 0, UserUpdate::Notifications),
                'unreadMessagesCount' => $live(fn (): int => $user?->remotes_enabled ? resolve(ConversationsQuery::class)->unreadCount($user) : 0, UserUpdate::Messages, UserUpdate::Inbox),
                'remotes' => $live(fn (): ?array => $user?->remotes_enabled ? [
                    'from' => $user->remotes_from,
                    'pull_receipts' => $user->pull_receipts,
                    'requests' => (bool) config('remotes.requests'),
                    'everyone' => (bool) config('remotes.everyone'),
                ] : null, UserUpdate::Account),
                'pasteis' => $live(fn (): ?array => $user?->pasteis_mode ? [
                    'remaining' => $user->pasteisRemaining(),
                    'day' => today()->toDateString(),
                ] : null, UserUpdate::Account),
            ],
            // Guests see how many developers are here, next to the sign-in button...
            'developersCount' => fn (): ?int => $user instanceof User ? null : resolve(DevelopersCountQuery::class)->get(),
            'hasUnmoderatedCommits' => $isAdmin ? $live(fn (): bool => Commit::query()->pending()->exists(), UserUpdate::Moderation) : false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'showsWhoToFollow' => $showsWhoToFollow,
            ...$user instanceof User && $hasAside ? [
                // Drawn again when someone followed goes live or stops, never on its own, so it doesn't reshuffle.
                'liveFollowing' => Inertia::once(fn (): Collection => resolve(LiveFollowingQuery::class)->get($user))->until(60)
                    ->live(UserUpdate::Live->value, new PrivateChannel($user)),
                'hotTopics' => Inertia::once(fn (): array => array_map(
                    fn (array $headline): array => [
                        'id' => $headline['id'],
                        'name' => $headline['name'],
                    ],
                    resolve(HotHeadlinesQuery::class)->get($user),
                ))->until(60)
                    // Read again at most once a minute however busy the headlines get, since everyone listens.
                    ->live('headlines', new Channel('headlines'), throttle: 60_000),
            ] : [],
            'dimsLanes' => $request->cookie('lane_dimming') !== 'false',
            'repliesPerPage' => RepliesPerPage::of($request)->value,
            'clock' => ReaderClock::of($request)->toArray(),
            // Relative times count from it on the server and while the browser hydrates the page, so both agree.
            'now' => now()->getTimestampMs(),
            // Onboarding and the operations console have no aside. Contextual suggestions must not reuse the once-cached list across pages.
            ...$suggestedUsers !== null ? [
                'suggestedUsers' => ($context === null ? $suggestedUsers->once() : $suggestedUsers)->merge()->matchOn('id'),
            ] : [],
        ];
    }
}
