<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class FollowingTimelineQuery implements TimelineQuery
{
    /**
     * @return Builder<Post>
     */
    public function builder(User $user): Builder
    {
        $following = $user->following();

        return Post::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('user_id', $following->newPivotQuery()->select($following->getRelatedPivotKeyName()))
                ->orWhere('user_id', $user->id))
            ->with('user')
            ->latest()
            ->latest('id');
    }
}
