<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class NewcomerFollowsQuery
{
    /**
     * How the people who signed up in the range built their network in their first 24 hours: the median number of
     * people they followed themselves, and how many of those follows were followed back since. Follows imported from
     * GitHub are left out: they are created in the same transaction that records the import, on the follower or the
     * followed side, so the ones made in the 10 seconds before either import are taken for them. Bots are left out,
     * and signups younger than 24 hours wait their turn.
     *
     * @return array{newcomers: int, median_follows: float|null, follows: int, followed_back: int}
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var object{newcomers: int, median_follows: float|null, follows: int, followed_back: int} $row */
        $row = DB::selectOne(<<<'SQL'
            with humans as not materialized (
                select id, created_at, github_follows_imported_at from users where github_id <> all(?::text[])
            ), newcomers as (
                select id, created_at, github_follows_imported_at from humans
                where created_at >= ? and created_at < ? and created_at <= ?
            ), follows_made as (
                select n.id as newcomer_id, exists (
                    select 1 from follows b where b.follower_id = f.followed_id and b.followed_id = n.id
                ) as followed_back
                from newcomers n
                join follows f on f.follower_id = n.id and f.created_at <= n.created_at + interval '24 hours'
                join humans followed on followed.id = f.followed_id
                where not coalesce(f.created_at between n.github_follows_imported_at - interval '10 seconds' and n.github_follows_imported_at, false)
                and not coalesce(f.created_at between followed.github_follows_imported_at - interval '10 seconds' and followed.github_follows_imported_at, false)
            )
            select
                (select count(*) from newcomers) as newcomers,
                (
                    select percentile_cont(0.5) within group (order by coalesce(made.follows, 0))
                    from newcomers n
                    left join (select newcomer_id, count(*) as follows from follows_made group by newcomer_id) made on made.newcomer_id = n.id
                ) as median_follows,
                (select count(*) from follows_made) as follows,
                (select count(*) from follows_made where followed_back) as followed_back
            SQL, [
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
            now()->subDay(),
        ]);

        return [
            'newcomers' => (int) $row->newcomers,
            'median_follows' => $row->median_follows === null ? null : (float) $row->median_follows,
            'follows' => (int) $row->follows,
            'followed_back' => (int) $row->followed_back,
        ];
    }
}
