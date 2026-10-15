<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

final readonly class SuggestedUsersQuery
{
    private const int PopularPoolSize = 20;

    /**
     * People the user does not follow yet: a mix of people their follows already follow and a few popular people.
     *
     * @return Collection<int, User>
     */
    public function get(User $user, int $limit = 3): Collection
    {
        $randomizer = $this->randomizerFor($user);
        $popularSlots = $randomizer->getInt(1, max(1, $limit - 1));

        $following = $user->following();
        $followingIds = $following->newPivotQuery()->select($following->getRelatedPivotKeyName());
        $followedByFollowing = DB::table('follows')->select('followed_id')->whereIn('follower_id', $followingIds);

        $network = $this->candidates($user, $followingIds)
            ->whereIn('id', $followedByFollowing)
            ->orderByDesc('followed_by_following_count')
            ->orderByDesc('followers_count')
            ->latest('id')
            ->limit($limit)
            ->get();

        $popularPool = $this->candidates($user, $followingIds)
            ->whereNotIn('id', $followedByFollowing)
            ->orderByDesc('followers_count')
            ->latest('id')
            ->limit(self::PopularPoolSize)
            ->get();

        $popular = array_slice(
            $randomizer->shuffleArray($popularPool->all()),
            0,
            $limit - min($network->count(), $limit - $popularSlots),
        );

        $network = $network->take($limit - count($popular));

        return new Collection($randomizer->shuffleArray([...$network->all(), ...$popular]));
    }

    /**
     * @return Builder<User>
     */
    private function candidates(User $user, QueryBuilder $followingIds): Builder
    {
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
            ]);
    }

    /**
     * The mix, the popular picks, and their order change once a day, so suggestions don't reshuffle on every page.
     */
    private function randomizerFor(User $user): Randomizer
    {
        return new Randomizer(new Mt19937(crc32($user->id.now()->toDateString())));
    }
}
