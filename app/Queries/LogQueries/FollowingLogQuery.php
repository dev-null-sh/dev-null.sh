<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class FollowingLogQuery implements LogQuery
{
    /**
     * @return Builder<Commit>
     */
    public function builder(User $user): Builder
    {
        $following = $user->following();

        return Commit::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('user_id', $following->newPivotQuery()->select($following->getRelatedPivotKeyName()))
                ->orWhere('user_id', $user->id))
            ->visibleTo($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->with(['user', 'linkPreview'])
            ->withStarsFor($user)
            ->latest()
            ->latest('id');
    }
}
