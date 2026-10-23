<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type BulkRow object{user_id: string, count: int, reach: int|null, newcomers: int|null}
 * @phpstan-type BulkAccount array{user: User, count: int, reach: int|null, newcomers: int|null}
 * @phpstan-type BulkActivity array{follows: list<BulkAccount>, stars: list<BulkAccount>, replies: list<BulkAccount>, posts: list<BulkAccount>}
 */
final readonly class BulkActivityQuery
{
    /**
     * The accounts that followed, starred, replied or posted the most since the given moment, the busiest first, bots
     * left out. Newcomers are the people reached who signed up since then too, which tells an account working through
     * every new signup from an active member. Deleted commits still count: what was removed was still sent.
     *
     * - Follows: people followed, leaving out the follows imported from GitHub, created in the transaction that records
     *   either side's import, so the 10 seconds before it.
     * - Stars: stars given, with the authors they went to.
     * - Replies: replies to someone else, with the people replied to.
     * - Posts: top-level commits, quotes included.
     *
     * @return BulkActivity
     */
    public function get(CarbonImmutable $since, int $limit = 10): array
    {
        $bots = User::botGitHubIdsArray();

        /** @var array<'follows'|'stars'|'replies'|'posts', list<BulkRow>> $rows */
        $rows = [
            'follows' => DB::select(<<<'SQL'
                select f.follower_id as user_id, count(*) as count, null as reach,
                    count(*) filter (where followed.created_at >= ?) as newcomers
                from follows f
                join users follower on follower.id = f.follower_id
                join users followed on followed.id = f.followed_id
                where follower.github_id <> all(?::text[]) and f.created_at >= ?
                and not coalesce(f.created_at between follower.github_follows_imported_at - interval '10 seconds' and follower.github_follows_imported_at, false)
                and not coalesce(f.created_at between followed.github_follows_imported_at - interval '10 seconds' and followed.github_follows_imported_at, false)
                group by f.follower_id
                order by count desc, f.follower_id
                limit ?
                SQL, [$since, $bots, $since, $limit]),
            'stars' => DB::select(<<<'SQL'
                select s.user_id, count(*) as count, count(distinct c.user_id) as reach,
                    count(distinct c.user_id) filter (where author.created_at >= ?) as newcomers
                from stars s
                join users starrer on starrer.id = s.user_id
                join commits c on c.id = s.commit_id
                join users author on author.id = c.user_id
                where starrer.github_id <> all(?::text[]) and s.created_at >= ?
                group by s.user_id
                order by count desc, s.user_id
                limit ?
                SQL, [$since, $bots, $since, $limit]),
            'replies' => DB::select(<<<'SQL'
                select r.user_id, count(*) as count, count(distinct p.user_id) as reach,
                    count(distinct p.user_id) filter (where author.created_at >= ?) as newcomers
                from commits r
                join users replier on replier.id = r.user_id
                join commits p on p.id = r.parent_id
                join users author on author.id = p.user_id
                where replier.github_id <> all(?::text[]) and r.created_at >= ? and p.user_id <> r.user_id
                group by r.user_id
                order by count desc, r.user_id
                limit ?
                SQL, [$since, $bots, $since, $limit]),
            'posts' => DB::select(<<<'SQL'
                select c.user_id, count(*) as count, null as reach, null as newcomers
                from commits c
                join users poster on poster.id = c.user_id
                where poster.github_id <> all(?::text[]) and c.created_at >= ? and c.parent_id is null
                group by c.user_id
                order by count desc, c.user_id
                limit ?
                SQL, [$bots, $since, $limit]),
        ];

        $users = User::query()
            ->whereIn('id', collect($rows)->flatten(1)->pluck('user_id')->unique()->all())
            ->get()
            ->keyBy('id');

        return [
            'follows' => $this->accounts($rows['follows'], $users),
            'stars' => $this->accounts($rows['stars'], $users),
            'replies' => $this->accounts($rows['replies'], $users),
            'posts' => $this->accounts($rows['posts'], $users),
        ];
    }

    /**
     * @param  list<BulkRow>  $rows
     * @param  Collection<array-key, User>  $users
     * @return list<BulkAccount>
     */
    private function accounts(array $rows, Collection $users): array
    {
        $accounts = [];

        foreach ($rows as $row) {
            $user = $users->get($row->user_id);

            if ($user instanceof User) {
                $accounts[] = [
                    'user' => $user,
                    'count' => (int) $row->count,
                    'reach' => $row->reach === null ? null : (int) $row->reach,
                    'newcomers' => $row->newcomers === null ? null : (int) $row->newcomers,
                ];
            }
        }

        return $accounts;
    }
}
