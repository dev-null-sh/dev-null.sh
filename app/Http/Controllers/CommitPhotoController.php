<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dto\ThreadContinuation;
use App\Http\Requests\ShowCommitPhotoRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitContinuationQuery;
use App\Queries\CommitRepliesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitPhotoController
{
    public function show(ShowCommitPhotoRequest $request, #[CurrentUser] ?User $currentUser, User $user, Commit $commit, int $photo, CommitAncestorsQuery $ancestors, CommitContinuationQuery $continuation, CommitRepliesQuery $replies): RedirectResponse|Response
    {
        if (! $currentUser instanceof User) {
            return to_route('commit.show', [$user, $commit]);
        }

        $focusedCommit = null;
        $getCommit = function () use (&$focusedCommit, $commit, $currentUser): Commit {
            return $focusedCommit ??= Commit::query()
                ->withVisibleReplyCount($currentUser)
                ->withContinuationId($currentUser)
                ->with(['user', 'linkPreview', 'images'])
                ->withQuotedCommitFor($currentUser)
                ->withStarsFor($currentUser)
                ->findOrFail($commit->id);
        };

        $threadContinuation = null;
        $getContinuation = function () use (&$threadContinuation, $continuation, $getCommit, $currentUser): ThreadContinuation {
            return $threadContinuation ??= $continuation->get($getCommit(), $currentUser);
        };

        return Inertia::render('commit/show', [
            'commit' => $getCommit,
            'ancestors' => fn (): Collection => $ancestors->get($commit, $currentUser),
            'continuation' => $getContinuation,
            'replies' => fn (): ?CursorPaginator => $getContinuation()->commits->isEmpty() ? $replies->paginate($commit, $currentUser, limit: $request->repliesPerPage()) : null,
            'photo' => fn (): array => [
                'commit' => $getCommit(),
                'index' => $photo - 1,
            ],
        ]);
    }
}
