<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommit;
use App\Actions\DeleteCommit;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRequest;
use App\Http\Requests\DeleteCommitRequest;
use App\Http\Requests\ShowCommitRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitRepliesQuery;
use App\Queries\CommitSeriesQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitController
{
    /**
     * Commits are public, so guests and crawlers read the same page, with the commit's link preview.
     */
    public function show(ShowCommitRequest $request, #[CurrentUser] ?User $currentUser, User $user, Commit $commit, CommitAncestorsQuery $ancestors, CommitRepliesQuery $replies, CommitSeriesQuery $series, PageMetadata $metadata): Response
    {
        $preview = $currentUser instanceof User ? null : $metadata->commit($commit->loadPublicPreview()->loadCount('stars'));

        $commit = Commit::query()
            ->withVisibleReplyCount($currentUser)
            ->withSavedState($currentUser)
            ->with(['user', 'linkPreview', 'images'])
            ->withQuotedCommitFor($currentUser)
            ->withStarsFor($currentUser)
            ->findOrFail($commit->id);

        $isInSeries = $commit->series_id !== null;

        return Inertia::render('commit/show', [
            'commit' => $commit,
            'ancestors' => $isInSeries ? $series->before($commit, $currentUser) : $ancestors->get($commit, $currentUser),
            'series' => $series->after($commit, $currentUser),
            // A series reads in order, so the replies to a part stay folded until asked for...
            'replies' => $isInSeries ? null : $replies->paginate($commit, $currentUser),
        ])->withViewData(['preview' => $preview]);
    }

    public function store(CreateCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action): RedirectResponse
    {
        $commit = $action->handle($user, $request->string('body')->value(), $request->parent(), $request->quotedCommit(), $request->images(), $request->linkPreview());

        Inertia::flash('committed', [
            'id' => $commit->id,
            'body' => $commit->body,
        ]);

        if ($commit->status === CommitStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Your commit is pending review. Everyone will see it once it is approved.'),
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

        if (url()->previous() === route('commit.show', [$commit->user, $commit])) {
            return to_route('user.show', $commit->user);
        }

        return back();
    }
}
