<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitRestoration;
use App\Http\Requests\CreateCommitRestorationRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitRestorationController
{
    public function store(CreateCommitRestorationRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitRestoration $action): RedirectResponse
    {
        $commit = $action->handle($commit);

        $restored = Commit::query()->withVisibleReplyCount($user)->withStarsFor($user)->withSavedState($user)->withMax([
            'bookmarks as saved_order' => fn (Builder $query): Builder => $query->where('user_id', $user->id),
        ], 'id')->with([
            'user',
            'linkPreview',
            'images',
            'quotedCommit' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->visibleTo($user)->withVisibleReplyCount($user)->with('user'),
            'parent' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->visibleTo($user)->withVisibleReplyCount($user)->with('user'),
        ])->findOrFail($commit->id);

        Inertia::flash('restored', $restored->setAttribute('is_pinned', $restored->user?->pinned_commit_id === $restored->id));

        return back();
    }
}
