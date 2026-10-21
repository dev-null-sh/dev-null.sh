<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class StarredCommitsQuery
{
    public function builder(User $user): CommitBuilder
    {
        return Commit::query()
            ->join('stars', 'stars.commit_id', '=', 'commits.id')
            ->where('stars.user_id', $user->id)
            ->select('commits.*')
            ->addSelect('stars.created_at as starred_order')
            ->visibleTo($user)
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->with([
                'parent' => function (BelongsTo $parent) use ($user): void {
                    $parent->getQuery()->withTrashed()->visibleTo($user)->with('user');
                },
            ])
            ->orderByDesc('starred_order')->orderByDesc('commits.id');
    }
}
