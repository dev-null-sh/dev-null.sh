<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class SavedCommitsQuery
{
    public function builder(User $user): CommitBuilder
    {
        return Commit::query()
            ->join('bookmarks', 'bookmarks.commit_id', '=', 'commits.id')
            ->where('bookmarks.user_id', $user->id)
            ->select('commits.*')
            ->addSelect('bookmarks.id as saved_order')
            ->visibleTo($user)
            ->withVisibleReplyCount($user)
            ->withSavedState($user)
            ->withStarsFor($user)
            ->with([
                'user',
                'linkPreview',
                'images',
                'parent' => function (BelongsTo $parent) use ($user): void {
                    $parent->getQuery()->withTrashed()->visibleTo($user)->with('user');
                },
            ])
            ->orderByDesc('saved_order');
    }
}
