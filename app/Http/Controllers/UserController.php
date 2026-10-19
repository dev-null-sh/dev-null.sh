<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUser;
use App\Enums\PushAccess;
use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\ShowUserRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\PendingFollowersQuery;
use App\Queries\PendingMentionCommitsQuery;
use App\Queries\PinnedCommitQuery;
use App\Queries\PushAccessQuery;
use App\Queries\UserAchievementsQuery;
use App\Queries\UserActivityQuery;
use App\Queries\UserCommitsQuery;
use App\Queries\UserStreakQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    /**
     * Profiles are public, so guests and crawlers read the same page, with the profile's link preview. A developer who
     * isn't on /dev/null yet has one too, waiting to be claimed, once someone mentions them.
     */
    public function show(ShowUserRequest $request, #[CurrentUser] ?User $currentUser, UserActivityQuery $activity, UserStreakQuery $streaks, UserAchievementsQuery $achievements, PushAccessQuery $pushAccess, UserCommitsQuery $commits, PinnedCommitQuery $pinned, PendingMentionCommitsQuery $mentions, PendingFollowersQuery $pendingFollowers, PageMetadata $metadata): Response|RedirectResponse
    {
        $user = $request->profile();

        if (! $user instanceof User) {
            return $this->unclaimed($request, $currentUser, $mentions, $pendingFollowers, $metadata);
        }

        $blockerId = $currentUser?->blockerIdWith($user);

        if ($blockerId !== null) {
            return Inertia::render('user/unavailable', [
                'username' => $user->username,
                'blockedUser' => $blockerId === $currentUser->id ? $user : null,
            ]);
        }

        $streakData = null;
        $getStreaks = function () use (&$streakData, $streaks, $user, $currentUser): array {
            return $streakData ??= $streaks->get($user, $currentUser);
        };

        $countedUser = null;
        $getCountedUser = function () use (&$countedUser, $user, $currentUser): User {
            return $countedUser ??= $currentUser instanceof User ? $user->loadCounts($currentUser) : $user->loadPublicCounts();
        };

        return Inertia::render('user/show', [
            'user' => $getCountedUser,
            'pasteisReceived' => $currentUser?->pasteis_mode ? $user->pasteis_received : null,
            'isFollowing' => fn (): bool => $currentUser?->isFollowing($user) ?? false,
            'followsYou' => fn (): bool => $currentUser instanceof User && $user->isFollowing($currentUser),
            'isMuting' => fn (): bool => $currentUser?->isMuting($user) ?? false,
            'canPush' => fn (): bool => $currentUser instanceof User
                && $currentUser->canMessage($user)
                && ! in_array($pushAccess->get($currentUser, $user), [PushAccess::Restricted, PushAccess::Closed], true),
            'activity' => fn (): array => $activity->get($user, $currentUser),
            'streaks' => $getStreaks,
            'achievements' => fn (): array => $achievements->get($user, $currentUser, $getStreaks()),
            'tab' => $request->tab(...),
            'pinnedCommit' => fn (): ?Commit => $request->tab() === 'replies' ? null : $pinned->get($user, $currentUser),
            'commits' => Inertia::scroll(fn () => $commits->builder($user, $currentUser, $request->tab() === 'replies')
                ->cursorPaginate(20)
                ->appends(['tab' => $request->tab()])),
        ])->withViewData(['preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->user($getCountedUser())]);
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
     * The profile of a developer who isn't on /dev/null yet, waiting for them to claim it: what GitHub says about them,
     * who is waiting to follow them, and the commits that mention them. A GitHub account renamed since its owner last
     * signed in goes to the profile they already have, under its old username.
     */
    private function unclaimed(ShowUserRequest $request, ?User $currentUser, PendingMentionCommitsQuery $mentions, PendingFollowersQuery $pendingFollowers, PageMetadata $metadata): Response|RedirectResponse
    {
        $account = $request->account();
        $owner = User::query()->where('github_id', $account->id)->first();

        if ($owner instanceof User) {
            return to_route('user.show', $owner);
        }

        $waiting = null;
        $getWaiting = function () use (&$waiting, $pendingFollowers, $account, $currentUser): array {
            return $waiting ??= $pendingFollowers->get($account->id, $currentUser);
        };

        return Inertia::render('user/unclaimed', [
            'account' => $account->toArray(),
            'waiting' => $getWaiting,
            'commits' => Inertia::scroll(fn () => $mentions->builder($account->profile->username, $currentUser)->cursorPaginate(20)),
        ])->withViewData(['preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->unclaimed($account, $getWaiting()['count'])]);
    }
}
