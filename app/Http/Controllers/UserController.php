<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUser;
use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\ShowUserRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\UserAchievementsQuery;
use App\Queries\UserActivityQuery;
use App\Queries\UserStreakQuery;
use App\Services\PageMetadata;
use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    /**
     * Profiles are public, so guests and crawlers read the same page, with the profile's link preview.
     */
    public function show(ShowUserRequest $request, #[CurrentUser] ?User $currentUser, User $user, UserActivityQuery $activity, UserStreakQuery $streaks, UserAchievementsQuery $achievements, PageMetadata $metadata): Response
    {
        $blockerId = $currentUser?->blockerIdWith($user);

        if ($blockerId !== null) {
            return Inertia::render('user/unavailable', [
                'username' => $user->username,
                'blockedUser' => $blockerId === $currentUser->id ? $user : null,
            ]);
        }

        $streakData = null;
        $getStreaks = function () use (&$streakData, $streaks, $user, $currentUser): array {
            return $streakData ??= $this->rememberForGuests($currentUser, "user-profile:{$user->id}:streaks", fn (): array => $streaks->get($user, $currentUser));
        };

        // Guests need the public counts for the link preview too, so they are counted once for both...
        $publicUser = $currentUser instanceof User ? null : $user->loadPublicCounts();

        return Inertia::render('user/show', [
            'user' => fn (): User => $publicUser ?? $user->loadCounts($currentUser),
            'isFollowing' => fn (): bool => $currentUser?->isFollowing($user) ?? false,
            'followsYou' => fn (): bool => $currentUser instanceof User && $user->isFollowing($currentUser),
            'isMuting' => fn (): bool => $currentUser?->isMuting($user) ?? false,
            'activity' => fn (): array => $this->rememberForGuests($currentUser, "user-profile:{$user->id}:activity", fn (): array => $activity->get($user, $currentUser)),
            'streaks' => $getStreaks,
            'achievements' => fn (): array => $this->rememberForGuests($currentUser, "user-profile:{$user->id}:achievements", fn (): array => $achievements->get($user, $currentUser, $getStreaks())),
            'tab' => $request->tab(...),
            'pinnedCommit' => fn (): ?Commit => $request->tab() === 'replies' || $user->pinned_commit_id === null ? null : $user->commits()
                ->visibleTo($currentUser)
                ->topLevel()
                ->withVisibleReplyCount($currentUser)
                ->withReplyPreviews($currentUser)
                ->withCardDataFor($currentUser)
                ->find($user->pinned_commit_id),
            'commits' => Inertia::scroll(fn () => $user->commits()
                ->visibleTo($currentUser)
                ->when(
                    $request->tab() === 'replies',
                    fn (CommitBuilder $query): CommitBuilder => $query
                        ->whereNotNull('parent_id')
                        ->exceptSeriesContinuations()
                        ->with(['parent' => fn (BelongsTo $query): BelongsTo => $query
                            ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($currentUser))
                            ->with('user')]),
                    fn (CommitBuilder $query): CommitBuilder => $query->topLevel()
                        ->when($user->pinned_commit_id, fn (CommitBuilder $query, string $id): CommitBuilder => $query->whereKeyNot($id)),
                )
                ->withVisibleReplyCount($currentUser)
                ->withReplyPreviews($currentUser)
                ->withCardDataFor($currentUser)
                ->latest()
                ->latest('id')
                ->cursorPaginate(20)
                ->appends(['tab' => $request->tab()])),
        ])->withViewData(['preview' => $publicUser instanceof User ? $metadata->user($publicUser) : null]);
    }

    public function destroy(DeleteUserRequest $request, #[CurrentUser] User $user, DeleteUser $action): RedirectResponse
    {
        Auth::logout();

        $action->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }

    /**
     * Guests, crawlers mostly, all read the same stats off a profile, which are costly to count for prolific users,
     * so they get them a few minutes late.
     *
     * @template TValue of array
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function rememberForGuests(?User $viewer, string $key, Closure $callback): array
    {
        return $viewer instanceof User ? $callback() : Cache::remember($key, now()->addMinutes(5), $callback);
    }
}
