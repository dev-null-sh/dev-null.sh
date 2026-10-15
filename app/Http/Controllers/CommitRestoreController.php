<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RestoreCommit;
use App\Http\Requests\RestoreCommitRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitRestoreController
{
    public function __invoke(RestoreCommitRequest $request, #[CurrentUser] User $user, Commit $commit, RestoreCommit $action): RedirectResponse
    {
        $commit = $action->handle($user, $commit);

        $restored = Commit::query()->withVisibleReplyCount($user)->withStarsFor($user)->withSavedState($user)->withMax([
            'bookmarks as saved_order' => fn (Builder $query): Builder => $query->where('user_id', $user->id),
        ], 'id')->with([
            'user',
            'linkPreview',
            'parent' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->visibleTo($user)->withVisibleReplyCount($user)->with('user'),
        ])->findOrFail($commit->id);

        Inertia::flash('restored', $restored->setAttribute('is_pinned', $restored->user->pinned_commit_id === $restored->id));

        return back();
    }
}
