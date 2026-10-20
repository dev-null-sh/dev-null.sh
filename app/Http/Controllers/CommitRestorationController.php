<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitRestoration;
use App\Http\Requests\CreateCommitRestorationRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitRestorationController
{
    public function store(CreateCommitRestorationRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitRestoration $action): RedirectResponse
    {
        $commit = $action->handle($commit);

        $restored = Commit::query()->withVisibleReplyCount($user)->withReplyPreviews($user)->withStarsFor($user)->addSelect(['starred_order' => $user->starredCommits()->newPivotStatement()->select('created_at')->whereColumn('commit_id', 'commits.id')->where('user_id', $user->id)->limit(1)])->with([
            'user',
            'linkPreview',
            'images',
            'quotedCommit' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->visibleTo($user)->withVisibleReplyCount($user)->withContinuationId($user)->with(['user', 'linkPreview', 'images'])->withPollFor($user),
            'parent' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->visibleTo($user)->withVisibleReplyCount($user)->withContinuationId($user)->with('user'),
        ])->findOrFail($commit->id);

        Inertia::flash('restored', $restored->setAttribute('is_pinned', $restored->user?->pinned_commit_id === $restored->id));

        return back();
    }
}
