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
     * People the user does not follow yet: those their follows already follow, with a few popular people mixed in.
     *
     * @return Collection<int, User>
     */
    public function get(User $user, int $limit = 6, int $popular = 2): Collection
    {
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

        $picked = new Collection($this->randomizerFor($user)->shuffleArray($popularPool->all()))
            ->take($limit - min($network->count(), $limit - $popular))
            ->values();

        $network = $network->take($limit - $picked->count());

        $pairs = $network->chunk(2);

        return new Collection($pairs
            ->values()
            ->flatMap(fn (Collection $pair, int $index): array => [...$pair->values(), ...$picked->slice($index, 1)])
            ->concat($picked->slice($pairs->count()))
            ->all());
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
     * Popular picks change once a day, so suggestions don't reshuffle on every page.
     */
    private function randomizerFor(User $user): Randomizer
    {
        return new Randomizer(new Mt19937(crc32($user->id.now()->toDateString())));
    }
}
