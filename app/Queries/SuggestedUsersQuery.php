<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

final readonly class SuggestedUsersQuery
{
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

        $network = $this->candidates($user, $following, $followingIds)
            ->whereIn('id', $followedByFollowing)
            ->orderByDesc('followed_by_following_count')
            ->orderByDesc('followers_count')
            ->latest('id')
            ->limit($limit * 10)
            ->get();

        $popularPool = $this->candidates($user, $following, $followingIds)
            ->whereNotIn('id', $followedByFollowing)
            ->orderByDesc('followers_count')
            ->latest('id')
            ->limit(20)
            ->get();

        $pages = $this->pages($randomizer, $network, $popularPool, $limit);

        return $pages[max(0, $round) % count($pages)];
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
     * @param  BelongsToMany<User, User>  $following
     * @return Builder<User>
     */
    private function candidates(User $user, BelongsToMany $following, QueryBuilder $followingIds): Builder
    {
        $candidates = User::query();

        $followersCount = fn (): QueryBuilder => $following->newPivotStatement()
            ->selectRaw('count(*)')
            ->whereColumn($following->getQualifiedRelatedPivotKeyName(), $candidates->qualifyColumn('id'));

        return $candidates
            ->whereKeyNot($user->id)
            ->whereNotIn('id', $followingIds)
            ->whereNotIn('id', $user->mutedIds())
            ->whereIn('id', Commit::query()->visibleTo($user)->topLevel()->select('user_id'))
            ->addSelect([
                'followed_by_following_count' => $followersCount()->whereIn($following->getForeignPivotKeyName(), $followingIds),
                'followers_count' => $followersCount(),
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
