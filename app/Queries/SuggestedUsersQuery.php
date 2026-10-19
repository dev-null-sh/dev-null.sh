<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

final readonly class SuggestedUsersQuery
{
    private const int POPULAR_POOL = 20;

    /**
     * Wide enough that someone following hundreds of the most followed still has 20 of them left to be suggested.
     */
    private const int MOST_FOLLOWED = 1000;

    private const int MOST_FOLLOWED_SECONDS = 3600;

    private const string MOST_FOLLOWED_KEY = 'suggested-users:most-followed';

    /**
     * People the user does not follow yet: a mix of people their follows already follow and a few
     * popular people picked from the 20 most followed. Either side fills in when the other runs short.
     *
     * The day's candidates are split into pages of that mix, and each round shows the next page,
     * going back to the first one after the last.
     *
     * @return Collection<int, User>
     */
    public function get(User $user, int $limit = 3, int $round = 0): Collection
    {
        $randomizer = $this->randomizerFor($user);

        $following = $user->following();
        $followingIds = $following->newPivotQuery()->select($following->getRelatedPivotKeyName());
        $followedByFollowing = $following->newPivotStatement()
            ->select($following->getRelatedPivotKeyName())
            ->whereIn($following->getForeignPivotKeyName(), $followingIds);

        $network = $this->candidates($user, $followingIds)
            ->joinSub(
                $followedByFollowing->clone()->selectRaw('count(*) as followed_by_following_count')->groupBy($following->getRelatedPivotKeyName()),
                'network',
                'network.'.$following->getRelatedPivotKeyName(),
                '=',
                'users.id',
            )
            ->select(['users.*', 'network.followed_by_following_count'])
            ->addSelect(['followers_count' => $this->followersCount($following)])
            ->whereExists(Commit::query()->visibleTo($user)->topLevel()->whereColumn('commits.user_id', 'users.id')->select('commits.id')->offset(0))
            ->orderByDesc('followed_by_following_count')
            ->orderByDesc('followers_count')
            ->latest('users.id')
            ->limit($limit * 10)
            ->get();

        $popularPool = $this->popularAmongMostFollowed($user, $followingIds, $following)
            ?? $this->popular($user, $followingIds, $following);

        $pages = $this->pages($randomizer, $network, $popularPool, $limit);

        return $pages[max(0, $round) % count($pages)];
    }

    /**
     * The popular picks for the user out of the ranking everyone shares, or null when too few of them are left for
     * the user. Everyone outside the ranking is less followed than anyone in it, so the picks are the same as ranking
     * every candidate, only with follower counts as old as the ranking.
     *
     * @param  BelongsToMany<User, User>  $following
     * @return Collection<int, User>|null
     */
    private function popularAmongMostFollowed(User $user, QueryBuilder $followingIds, BelongsToMany $following): ?Collection
    {
        $ranking = $this->mostFollowed();

        /** @var list<string> $left */
        $left = $this->candidates($user, $followingIds)
            ->whereIn('users.id', array_keys($ranking))
            ->whereNotExists($this->followedByFollowing($user, $following))
            ->whereNotIn('users.id', $user->idsAcrossBlocks())
            ->pluck('users.id')
            ->all();

        $picks = array_flip(array_slice(array_keys(array_intersect_key($ranking, array_flip($left))), 0, self::POPULAR_POOL));

        if (count($picks) < self::POPULAR_POOL) {
            return null;
        }

        return User::query()->findMany(array_keys($picks))
            ->sortBy(fn (User $pick): int => $picks[$pick->id])
            ->values()
            ->each(fn (User $pick): User => $pick->forceFill([
                'followed_by_following_count' => 0,
                'followers_count' => $ranking[$pick->id],
            ]))
            ->toBase();
    }

    /**
     * The most followed people with a visible top-level commit, and how many followers each has, most followed first.
     *
     * @return array<string, int>
     */
    private function mostFollowed(): array
    {
        /** @var array<string, int> $ranking */
        $ranking = Cache::remember(self::MOST_FOLLOWED_KEY, self::MOST_FOLLOWED_SECONDS, fn (): array => DB::query()
            ->fromSub(DB::table('follows')->select('followed_id')->selectRaw('count(*) as followers')->groupBy('followed_id'), 'ranked')
            ->whereExists(Commit::query()->approved()->topLevel()->whereColumn('commits.user_id', 'ranked.followed_id')->select('commits.id')->offset(0))
            ->orderByDesc('followers')
            ->orderByDesc('followed_id')
            ->limit(self::MOST_FOLLOWED)
            ->pluck('followers', 'followed_id')
            ->map(fn (mixed $followers): int => is_numeric($followers) ? (int) $followers : 0)
            ->all());

        return $ranking;
    }

    /**
     * Ranked before looking for commits, so only the most followed are looked up rather than every candidate.
     *
     * @param  BelongsToMany<User, User>  $following
     * @return Collection<int, User>
     */
    private function popular(User $user, QueryBuilder $followingIds, BelongsToMany $following): Collection
    {
        return User::query()
            ->fromSub($this->candidates($user, $followingIds)
                ->whereNotExists($this->followedByFollowing($user, $following))
                ->select('users.*')
                ->selectRaw('0 as followed_by_following_count')
                ->addSelect(['followers_count' => $this->followersCount($following)])
                ->orderByDesc('followers_count')
                ->latest('users.id'), 'users')
            ->whereIn('users.id', $this->committers($user))
            ->orderByDesc('followers_count')
            ->latest('users.id')
            ->limit(self::POPULAR_POOL)
            ->get()
            ->toBase();
    }

    /**
     * The last page is topped up from the first one, so a refresh never shows fewer people.
     *
     * @param  Collection<int, User>  $network
     * @param  Collection<int, User>  $popularPool
     * @return non-empty-list<Collection<int, User>>
     */
    private function pages(Randomizer $randomizer, Collection $network, Collection $popularPool, int $limit): array
    {
        $popular = null;
        $pages = [];

        do {
            $networkSlots = $limit - $randomizer->getInt(1, max(1, $limit - 1));
            $popular ??= new Collection($randomizer->shuffleArray($popularPool->all()));

            $pageNetwork = $network->take(max($networkSlots, $limit - $popular->count()));
            $pagePopular = $popular->take($limit - $pageNetwork->count());

            $network = $network->skip($pageNetwork->count());
            $popular = $popular->skip($pagePopular->count());

            $pages[] = $randomizer->shuffleArray([...$pageNetwork, ...$pagePopular]);
        } while ($network->isNotEmpty() || $popular->isNotEmpty());

        $last = array_key_last($pages);

        if ($last > 0 && count($pages[$last]) < $limit) {
            $pages[$last] = $randomizer->shuffleArray([...$pages[$last], ...array_slice($pages[0], 0, $limit - count($pages[$last]))]);
        }

        return array_map(fn (array $page): Collection => new Collection($page), $pages);
    }

    /**
     * @return Builder<User>
     */
    private function candidates(User $user, QueryBuilder $followingIds): Builder
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->whereNotIn('users.id', $followingIds)
            ->whereNotIn('users.id', $user->mutedIds());
    }

    /**
     * Whether someone the user follows follows the candidate. A not in over the same people is checked row by row
     * against every one of them once they outgrow the database's working memory, which takes minutes for someone
     * who follows a few thousand people, while a not exists is answered as an anti join.
     *
     * @param  BelongsToMany<User, User>  $following
     */
    private function followedByFollowing(User $user, BelongsToMany $following): QueryBuilder
    {
        $followed = $following->getRelatedPivotKeyName();
        $follower = $following->getForeignPivotKeyName();

        return $following->newPivotStatement()
            ->from($following->getTable().' as followed_by_following')
            ->join($following->getTable().' as followed_by_user', "followed_by_user.{$followed}", '=', "followed_by_following.{$follower}")
            ->where("followed_by_user.{$follower}", $user->id)
            ->whereColumn("followed_by_following.{$followed}", 'users.id');
    }

    /**
     * @param  BelongsToMany<User, User>  $following
     */
    private function followersCount(BelongsToMany $following): QueryBuilder
    {
        return $following->newPivotStatement()
            ->selectRaw('count(*)')
            ->whereColumn($following->getQualifiedRelatedPivotKeyName(), 'users.id');
    }

    private function committers(User $user): CommitBuilder
    {
        return Commit::query()->visibleTo($user)->topLevel()->select('user_id');
    }

    /**
     * The mix, the popular picks, and their order change once a day, so suggestions don't reshuffle on every page.
     */
    private function randomizerFor(User $user): Randomizer
    {
        return new Randomizer(new Mt19937(crc32($user->id.now()->toDateString())));
    }
}
