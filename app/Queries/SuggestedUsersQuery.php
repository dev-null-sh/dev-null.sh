<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class SuggestedUsersQuery
{
    /**
     * People with visible commits the user does not follow yet, ranked by how many of the user's follows already follow them.
     *
     * @return Collection<int, User>
     */
    public function get(User $user, int $limit = 3): Collection
    {
        $following = $user->following();
        $followingIds = $following->newPivotQuery()->select($following->getRelatedPivotKeyName());

        return User::query()
            ->whereKeyNot($user->id)
            ->whereNotIn('id', $followingIds)
            ->whereHas('commits', fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($user))
            ->addSelect([
                'followed_by_following_count' => DB::table('follows')
                    ->selectRaw('count(*)')
                    ->whereColumn('followed_id', 'users.id')
                    ->whereIn('follower_id', $followingIds),
                'followers_count' => DB::table('follows')
                    ->selectRaw('count(*)')
                    ->whereColumn('followed_id', 'users.id'),
            ])
            ->orderByDesc('followed_by_following_count')
            ->orderByDesc('followers_count')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
