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
        return Post::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereBelongsTo($user)
                ->orWhereIn('user_id', $user->following()->select('users.id')))
            ->with('user')
            ->latest()
            ->latest('id');
    }
}
