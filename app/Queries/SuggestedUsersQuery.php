<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

final readonly class SuggestedUsersQuery
{
    /**
     * Popular people come in groups of the next 20 most followed, each shuffled on its own once reached.
     */
    private const int POPULAR_CHUNK = 20;

    /**
     * How far rounds reach down the rankings before going back to the first page anyway.
     */
    private const int MAX_ROUND = 100;

    private const string THREAD_PARTICIPANTS = <<<'SQL'
        WITH RECURSIVE visible_thread(id, user_id) AS (
            SELECT id, user_id FROM commits WHERE id = ?
            UNION
            SELECT child.id, child.user_id
            FROM commits child
            JOIN visible_thread parent ON child.parent_id = parent.id OR child.quoted_commit_id = parent.id
            WHERE (child.status = ? OR child.user_id = ?)
                AND NOT EXISTS (SELECT 1 FROM blocks WHERE (blocker_id = ? AND blocked_id = child.user_id) OR (blocker_id = child.user_id AND blocked_id = ?))
                AND NOT EXISTS (SELECT 1 FROM mutes WHERE muter_id = ? AND muted_id = child.user_id)
                AND (child.deleted_at IS NULL OR EXISTS (
                    SELECT 1 FROM commits surviving_replies
                    WHERE (surviving_replies.parent_id = child.id OR surviving_replies.quoted_commit_id = child.id)
                        AND (surviving_replies.status = ? OR surviving_replies.user_id = ?)
                        AND NOT EXISTS (SELECT 1 FROM blocks WHERE (blocker_id = ? AND blocked_id = surviving_replies.user_id) OR (blocker_id = surviving_replies.user_id AND blocked_id = ?))
                        AND NOT EXISTS (SELECT 1 FROM mutes WHERE muter_id = ? AND muted_id = surviving_replies.user_id)
                ))
        )
        SELECT visible_thread.user_id
        FROM visible_thread
        JOIN commits visible_commits ON visible_commits.id = visible_thread.id
        WHERE visible_thread.user_id IS NOT NULL AND visible_commits.deleted_at IS NULL
        SQL;

    /**
     * People the user does not follow yet: a mix of people their follows already follow and a few
     * popular people picked from the 20 most followed. On a profile or discussion page, relevant developers come
     * first. The regular suggestions fill any remaining places.
     *
     * The candidates are split into pages of that mix, and each round shows the next page. Later rounds reach further
     * down both rankings, so a round only goes back to the first page once nobody new is left. Contextual pages go
     * back after their candidates and the day's first regular picks.
     *
     * Spares come after the page, taken from the pages that follow it, to fill the slot of someone dismissed without
     * reloading the rest.
     *
     * @return Collection<int, User>
     */
    public function get(User $user, int $limit = 3, int $round = 0, int $spares = 0, User|Commit|null $context = null): Collection
    {
        $round = max(0, $round);

        $randomizer = $this->randomizerFor($user);

        $following = $user->following();
        $followingIds = $following->newPivotQuery()->select($following->getRelatedPivotKeyName());
        if ($context instanceof User && $user->hasBlockWith($context)) {
            $context = null;
        }

        $contextUserId = $context instanceof User ? $context->id : ($context instanceof Commit ? $context->user_id : null);

        $contextual = $context instanceof User || $context instanceof Commit
            ? $this->contextual($user, $context, $followingIds, $following, $limit)
            : new Collection;

        $wraps = true;

        if ($contextual->count() >= $limit * 5 && $limit + $spares <= $contextual->count()) {
            $pages = $this->pages($randomizer, $contextual, new Collection, $limit, prioritizeNetwork: true);
        } else {
            // Enough of each ranking for every page up to this round and the spares after it.
            $wanted = $contextual->isEmpty() ? (min($round, self::MAX_ROUND) + 1) * $limit + $spares : 0;
            $networkLimit = max($limit * 10, $wanted);
            // Whole chunks, so a chunk is shuffled the same however far the rounds have reached.
            $popularLimit = self::POPULAR_CHUNK * max(1, (int) ceil($wanted / self::POPULAR_CHUNK));

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
                ->addSelect(['followers_count' => $this->followersCount($following), 'follows_you' => $this->followsUser($user)])
                ->whereIn('users.id', $this->committers($user))
                ->orderByDesc('followed_by_following_count')
                ->orderByDesc('followers_count')
                ->latest('users.id')
                ->limit($networkLimit)
                ->get();

            // Ranked before looking for commits, so only the most followed are looked up rather than every candidate.
            $popularPool = User::query()
                ->fromSub($this->candidates($user, $followingIds)
                    ->whereNotExists($this->followedByFollowing($user, $following))
                    ->select('users.*')
                    ->selectRaw('0 as followed_by_following_count')
                    ->addSelect(['followers_count' => $this->followersCount($following), 'follows_you' => $this->followsUser($user)])
                    ->orderByDesc('followers_count')
                    ->latest('users.id'), 'users')
                ->whereIn('users.id', $this->committers($user))
                ->orderByDesc('followers_count')
                ->latest('users.id')
                ->limit($popularLimit)
                ->get();

            $wraps = $contextual->isNotEmpty() || $round > self::MAX_ROUND
                || ($network->count() < $networkLimit && $popularPool->count() < $popularLimit);

            $pages = $this->pages($randomizer, $network, $popularPool, $limit, popularChunk: self::POPULAR_CHUNK, topUp: $wraps);

            if ($contextual->isNotEmpty()) {
                /** @var Collection<int, User> $general */
                $general = new Collection(array_merge(...array_map(fn (Collection $page): array => $page->all(), $pages)));
                $contextualIds = $contextual->pluck('id')->all();
                if ($contextUserId !== null) {
                    $contextualIds[] = $contextUserId;
                }

                $general = $general->unique('id')->whereNotIn('id', $contextualIds)->values();

                $pages = $this->pages($randomizer, $contextual, $general, $limit, prioritizeNetwork: true);
            }
        }

        $current = $wraps ? $round % count($pages) : $round;

        $rotated = [...array_slice($pages, $current), ...array_slice($pages, 0, $current)];

        return new Collection(array_merge(...array_map(fn (Collection $page): array => $page->all(), $rotated)))
            ->unique('id')
            ->take($limit + $spares)
            ->values();
    }

    /**
     * On a profile, suggest people that developer follows. In a discussion, suggest its visible participants.
     * The regular network and popular pools fill any remaining slots.
     *
     * @param  BelongsToMany<User, User>  $following
     * @return Collection<int, User>
     */
    private function contextual(User $viewer, User|Commit $context, QueryBuilder $followingIds, BelongsToMany $following, int $limit): Collection
    {
        if ($context instanceof User) {
            $ids = $context->following()->newPivotQuery()->select('followed_id');
        } else {
            $ids = DB::query()
                ->fromRaw('('.self::THREAD_PARTICIPANTS.') AS visible_thread_participants', [
                    $context->root_id ?? $context->id,
                    CommitStatus::Approved->value, $viewer->id, $viewer->id, $viewer->id, $viewer->id,
                    CommitStatus::Approved->value, $viewer->id, $viewer->id, $viewer->id, $viewer->id,
                ])
                ->select('visible_thread_participants.user_id');
        }

        return $this->candidates($viewer, $followingIds)
            ->whereIn('users.id', $ids)
            ->whereIn('users.id', $this->committers($viewer))
            ->when(
                $context instanceof User ? $context->id : $context->user_id,
                fn (Builder $query, string $id): Builder => $query->whereKeyNot($id),
            )
            ->select('users.*')
            ->selectRaw('0 as followed_by_following_count')
            ->addSelect(['followers_count' => $this->followersCount($following), 'follows_you' => $this->followsUser($viewer)])
            ->orderByDesc('followers_count')
            ->latest('users.id')
            ->limit($limit * 5)
            ->get()
            ->toBase();
    }

    /**
     * Popular people are shuffled a chunk at a time as the pages reach them, so earlier pages stay the same however far
     * the pool goes. When the pages go back to the first one, the last page is topped up from it, so a refresh never
     * shows fewer people.
     *
     * @param  Collection<int, User>  $network
     * @param  Collection<int, User>  $popularPool
     * @return non-empty-list<Collection<int, User>>
     */
    private function pages(Randomizer $randomizer, Collection $network, Collection $popularPool, int $limit, bool $prioritizeNetwork = false, int $popularChunk = PHP_INT_MAX, bool $topUp = true): array
    {
        $popular = new Collection;
        $pages = [];

        do {
            $networkSlots = $prioritizeNetwork ? $limit : $limit - $randomizer->getInt(1, max(1, $limit - 1));

            if ($pages === [] || ($popular->count() < $limit && $popularPool->isNotEmpty())) {
                $popular = new Collection([...$popular, ...$randomizer->shuffleArray($popularPool->take($popularChunk)->all())]);
                $popularPool = $popularPool->skip($popularChunk);
            }

            $pageNetwork = $network->take(max($networkSlots, $limit - $popular->count()));
            $pagePopular = $popular->take($limit - $pageNetwork->count());

            $network = $network->skip($pageNetwork->count());
            $popular = $popular->skip($pagePopular->count());

            $pages[] = $prioritizeNetwork
                ? [...$randomizer->shuffleArray($pageNetwork->all()), ...$randomizer->shuffleArray($pagePopular->all())]
                : $randomizer->shuffleArray([...$pageNetwork, ...$pagePopular]);
        } while ($network->isNotEmpty() || $popular->isNotEmpty() || $popularPool->isNotEmpty());

        $last = array_key_last($pages);

        if ($topUp && $last > 0 && count($pages[$last]) < $limit) {
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
            ->whereNotIn('users.id', $user->mutedIds())
            ->whereNotIn('users.id', $user->dismissedSuggestions()->newPivotQuery()->select('dismissed_id'));
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

    private function followsUser(User $user): QueryBuilder
    {
        $followers = $user->followers();

        return $followers->newPivotQuery()
            ->selectRaw('count(*) > 0')
            ->whereColumn($followers->getQualifiedRelatedPivotKeyName(), 'users.id');
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
