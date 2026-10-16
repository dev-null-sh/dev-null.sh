<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitPhotoRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitRepliesQuery;
use App\Queries\CommitSeriesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitPhotoController
{
    public function show(ShowCommitPhotoRequest $request, #[CurrentUser] ?User $currentUser, User $user, Commit $commit, int $photo, CommitAncestorsQuery $ancestors, CommitRepliesQuery $replies, CommitSeriesQuery $series): RedirectResponse|Response
    {
        if (! $currentUser instanceof User) {
            return to_route('commit.show', [$user, $commit]);
        }

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
            'replies' => $isInSeries ? null : $replies->paginate($commit, $currentUser),
            'photo' => [
                'commit' => $commit,
                'index' => $photo - 1,
            ],
        ]);
    }
}
