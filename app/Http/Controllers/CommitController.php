<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommit;
use App\Actions\DeleteCommit;
use App\Dto\ThreadContinuation;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRequest;
use App\Http\Requests\DeleteCommitRequest;
use App\Http\Requests\ShowCommitRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitContinuationQuery;
use App\Queries\CommitPageQuery;
use App\Queries\CommitRepliesQuery;
use App\Queries\UserAchievementsQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitController
{
    /**
     * Commits are public, so guests and crawlers read the same page, with the commit's link preview and the replies shown under it as structured data.
     */
    public function show(ShowCommitRequest $request, #[CurrentUser] ?User $currentUser, User $user, Commit $commit, CommitPageQuery $page, CommitAncestorsQuery $ancestors, CommitContinuationQuery $continuation, CommitRepliesQuery $replies, PageMetadata $metadata): Response
    {
        $focusedCommit = null;
        $getCommit = function () use (&$focusedCommit, $page, $commit, $currentUser): Commit {
            return $focusedCommit ??= $page->get($commit, $currentUser);
        };

        $threadContinuation = null;
        $getContinuation = function () use (&$threadContinuation, $continuation, $getCommit, $currentUser): ThreadContinuation {
            return $threadContinuation ??= $continuation->get($getCommit(), $currentUser);
        };

        $threadReplies = null;
        $getReplies = function () use (&$threadReplies, $replies, $commit, $currentUser, $request): CursorPaginator {
            return $threadReplies ??= $replies->paginate($commit, $currentUser, limit: $request->repliesPerPage());
        };

        $preview = $currentUser instanceof User || $request->inertia() ? null : $metadata->commit(
            $commit->loadPublicPreview()->loadCount('stars')->load(['images', 'linkPreview', 'quotedCommit.user']),
            $getReplies()->items(),
        );

        return Inertia::render('commit/show', [
            'commit' => $getCommit,
            'ancestors' => fn (): Collection => $ancestors->get($commit, $currentUser),
            'continuation' => $getContinuation,
            'replies' => fn (): ?CursorPaginator => $getContinuation()->commits->isEmpty() ? $getReplies() : null,
        ])->withViewData(['preview' => $preview]);
    }

    public function store(CreateCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action, UserAchievementsQuery $achievements): RedirectResponse
    {
        $before = $achievements->unlockedBadgeNames($user);

        $commit = $action->handle($user, $request->string('body')->value(), quotedCommit: $request->quotedCommit(), images: $request->images(), imageDescriptions: $request->imageDescriptions(), linkPreview: $request->linkPreview(), poll: $request->poll());

        $unlocked = $achievements->unlockedBadgeNames($user)->diff($before)->values();

        Inertia::flash('committed', [
            'id' => $commit->id,
            'body' => $commit->body,
            'user' => ['username' => $user->username],
        ]);

        if ($unlocked->isNotEmpty()) {
            Inertia::flash('achievementsUnlocked', $unlocked->all());
        }

        if ($commit->status === CommitStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'Your commit is pending review. Everyone will see it once it is approved.',
            ]);
        }

        return back();
    }

    public function destroy(DeleteCommitRequest $request, Commit $commit, DeleteCommit $action): RedirectResponse
    {
        $commit = $action->handle($commit);

        Inertia::flash('deleted', [
            'id' => $commit->id,
            'parent_id' => $commit->parent_id,
            'deleted_at' => $commit->deleted_at?->toJSON(),
            'expires_at' => $commit->deleted_at?->addSeconds(Commit::RESTORABLE_SECONDS)->toJSON(),
            'server_time' => now()->toJSON(),
        ]);

        $commitUrl = route('commit.show', [$commit->user, $commit]);

        if (Str::is([$commitUrl, "{$commitUrl}/photos/*"], url()->previous())) {
            return to_route('user.show', $commit->user);
        }

        return back();
    }
}
