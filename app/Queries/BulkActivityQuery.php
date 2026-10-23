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
 * @phpstan-type BulkThresholds array{follows: array{perDay: int, total: int}, stars: array{perDay: int, total: int}, replies: array{perDay: int, total: int}, posts: array{perDay: int, total: int}}
 */
final readonly class BulkActivityQuery
{
    /**
     * Following 50 people a day is past any onboarding: the account that followed every newcomer did about 1,300 a day.
     */
    private const int FOLLOWS_A_DAY = 50;

    /**
     * An engaged member stars a dozen people a day; the account that starred every newcomer reached about 130.
     */
    private const int STARRED_PEOPLE_A_DAY = 30;

    /**
     * Conversations reach a handful of people a day; 20 different people is canned replies.
     */
    private const int REPLIED_PEOPLE_A_DAY = 20;

    /**
     * Members post a few times a day; 15 top-level posts is a feed being filled.
     */
    private const int POSTS_A_DAY = 15;

    /**
     * The accounts that followed, starred, replied or posted the most in the last days, the busiest first, bots left
     * out, as long as they reached a threshold no normal use does: a rate a day times the days. Newcomers are the people
     * reached who signed up in that time too, which tells an account working through every new signup from an active
     * member. Deleted commits still count: what was removed was still sent.
     *
     * - Follows: people followed, leaving out the follows imported from GitHub, created in the transaction that records
     *   either side's import, so the 10 seconds before it.
     * - Stars: the people starred, with the stars given.
     * - Replies: the people replied to, other than themselves, with the replies written.
     * - Posts: top-level commits, quotes included.
     *
     * @return BulkActivity
     */
    public function get(int $days, int $limit = 10): array
    {
        $bots = User::botGitHubIdsArray();
        $since = CarbonImmutable::now()->subDays($days);
        $thresholds = $this->thresholds($days);

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
                having count(*) >= ?
                order by count desc, f.follower_id
                limit ?
                SQL, [$since, $bots, $since, $thresholds['follows']['total'], $limit]),
            'stars' => DB::select(<<<'SQL'
                select s.user_id, count(*) as count, count(distinct c.user_id) as reach,
                    count(distinct c.user_id) filter (where author.created_at >= ?) as newcomers
                from stars s
                join users starrer on starrer.id = s.user_id
                join commits c on c.id = s.commit_id
                join users author on author.id = c.user_id
                where starrer.github_id <> all(?::text[]) and s.created_at >= ?
                group by s.user_id
                having count(distinct c.user_id) >= ?
                order by reach desc, count desc, s.user_id
                limit ?
                SQL, [$since, $bots, $since, $thresholds['stars']['total'], $limit]),
            'replies' => DB::select(<<<'SQL'
                select r.user_id, count(*) as count, count(distinct p.user_id) as reach,
                    count(distinct p.user_id) filter (where author.created_at >= ?) as newcomers
                from commits r
                join users replier on replier.id = r.user_id
                join commits p on p.id = r.parent_id
                join users author on author.id = p.user_id
                where replier.github_id <> all(?::text[]) and r.created_at >= ? and p.user_id <> r.user_id
                group by r.user_id
                having count(distinct p.user_id) >= ?
                order by reach desc, count desc, r.user_id
                limit ?
                SQL, [$since, $bots, $since, $thresholds['replies']['total'], $limit]),
            'posts' => DB::select(<<<'SQL'
                select c.user_id, count(*) as count, null as reach, null as newcomers
                from commits c
                join users poster on poster.id = c.user_id
                where poster.github_id <> all(?::text[]) and c.created_at >= ? and c.parent_id is null
                group by c.user_id
                having count(*) >= ?
                order by count desc, c.user_id
                limit ?
                SQL, [$bots, $since, $thresholds['posts']['total'], $limit]),
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
     * What each list takes to appear on it, a day and over the given days.
     *
     * @return BulkThresholds
     */
    public function thresholds(int $days): array
    {
        $threshold = fn (int $perDay): array => ['perDay' => $perDay, 'total' => $perDay * $days];

        return [
            'follows' => $threshold(self::FOLLOWS_A_DAY),
            'stars' => $threshold(self::STARRED_PEOPLE_A_DAY),
            'replies' => $threshold(self::REPLIED_PEOPLE_A_DAY),
            'posts' => $threshold(self::POSTS_A_DAY),
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
