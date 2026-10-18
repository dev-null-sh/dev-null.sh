<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUser;
use App\Enums\PushAccess;
use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\ShowUserRequest;
use App\Models\Commit;
use App\Models\Talk;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\PushAccessQuery;
use App\Queries\UserAchievementsQuery;
use App\Queries\UserActivityQuery;
use App\Queries\UserStreakQuery;
use App\Services\Embeds;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    /**
     * Profiles are public, so guests and crawlers read the same page, with the profile's link preview.
     */
    public function show(ShowUserRequest $request, #[CurrentUser] ?User $currentUser, User $user, UserActivityQuery $activity, UserStreakQuery $streaks, UserAchievementsQuery $achievements, PushAccessQuery $pushAccess, PageMetadata $metadata, Embeds $embeds): Response
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
            'pinnedCommit' => fn (): ?Commit => in_array($request->tab(), ['replies', 'talks'], true) || $user->pinned_commit_id === null ? null : $user->commits()
                ->visibleTo($currentUser)
                ->topLevel()
                ->withVisibleReplyCount($currentUser)
                ->withReplyPreviews($currentUser)
                ->withCardDataFor($currentUser)
                ->find($user->pinned_commit_id),
            'commits' => Inertia::scroll(fn () => $user->commits()
                ->visibleTo($currentUser)
                ->when(
                    $request->tab() === 'talks',
                    fn (CommitBuilder $query): CommitBuilder => $query->whereRaw('0 = 1'),
                    fn (CommitBuilder $query): CommitBuilder => $query->when(
                        $request->tab() === 'replies',
                        fn (CommitBuilder $query): CommitBuilder => $query
                            ->whereNotNull('parent_id')
                            ->with(['parent' => fn (BelongsTo $query): BelongsTo => $query
                                ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($currentUser))
                                ->with('user')]),
                        fn (CommitBuilder $query): CommitBuilder => $query->topLevel()
                            ->when($user->pinned_commit_id, fn (CommitBuilder $query, string $id): CommitBuilder => $query->whereKeyNot($id)),
                    ),
                )
                ->withVisibleReplyCount($currentUser)
                ->withReplyPreviews($currentUser)
                ->withCardDataFor($currentUser)
                ->latest()
                ->latest('id')
                ->cursorPaginate(20)
                ->appends(['tab' => $request->tab()])),
            'talks' => Inertia::scroll(fn (): CursorPaginator => $user->talks()
                ->when(
                    $request->tab() !== 'talks',
                    fn ($query) => $query->whereRaw('0 = 1'),
                    fn ($query) => $query
                        ->whereNotNull('published_at')
                        ->whereNotNull('commit_id')
                        ->whereHas('commit', fn (CommitBuilder $commit): CommitBuilder => $commit->visibleTo($currentUser))
                        ->latest('presented_on')
                        ->latest('id'),
                )
                ->cursorPaginate(20)
                ->through(function (Talk $talk) use ($embeds): array {
                    $recordingEmbed = is_string($talk->recording_url) ? $embeds->for($talk->recording_url) : null;
                    $slidesEmbed = is_string($talk->slides_url) ? $embeds->for($talk->slides_url) : null;

                    return [
                        'id' => $talk->id,
                        'title' => $talk->title,
                        'description' => $talk->description,
                        'presented_on' => $talk->presented_on->toDateString(),
                        'slides_url' => $talk->slides_url,
                        'recording_url' => $talk->recording_url,
                        'event_name' => $talk->event_name,
                        'published_at' => $talk->published_at?->toIso8601String(),
                        'recording_embed' => $recordingEmbed?->toArray(),
                        'slides_embed' => $slidesEmbed?->toArray(),
                    ];
                })
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
}
