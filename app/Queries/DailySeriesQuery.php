<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * @phpstan-type DailyPoint array{date: string, signups: int, commits: int, replies: int, active_users: int, stars: int, follows: int, failed_jobs: int}
 */
final readonly class DailySeriesQuery
{
    /**
     * What happened each UTC day of the range, oldest first, every day listed even when nothing did: signups, top-level
     * commits and replies whatever their status since, deleted ones included, the people seen, stars, follows, and the
     * jobs that failed. Bots are left out of everything people do.
     *
     * @return non-empty-list<DailyPoint>
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $until = $to->addDay()->startOfDay();

        /** @var non-empty-list<object{date: string, signups: int, commits: int, replies: int, active_users: int, stars: int, follows: int}> $rows */
        $rows = DB::select(<<<'SQL'
            with bots as (
                select id from users where github_id = any(?::text[])
            ), bounds as (
                select ?::timestamp as from_at, ?::timestamp as until_at
            ), signups as (
                select created_at::date as date, count(*) as count from users, bounds
                where created_at >= from_at and created_at < until_at and id not in (select id from bots)
                group by 1
            ), posts as (
                select created_at::date as date, count(*) filter (where parent_id is null) as commits,
                    count(*) filter (where parent_id is not null) as replies
                from commits, bounds
                where created_at >= from_at and created_at < until_at and user_id not in (select id from bots)
                group by 1
            ), stars as (
                select created_at::date as date, count(*) as count from stars, bounds
                where created_at >= from_at and created_at < until_at and user_id not in (select id from bots)
                group by 1
            ), follows as (
                select created_at::date as date, count(*) as count from follows, bounds
                where created_at >= from_at and created_at < until_at and follower_id not in (select id from bots)
                group by 1
            )
            select days.date::date::text as date,
                coalesce(signups.count, 0) as signups,
                coalesce(posts.commits, 0) as commits,
                coalesce(posts.replies, 0) as replies,
                coalesce(active_user_counts.count, 0) as active_users,
                coalesce(stars.count, 0) as stars,
                coalesce(follows.count, 0) as follows
            from bounds, generate_series(bounds.from_at, bounds.until_at - interval '1 day', interval '1 day') as days(date)
            left join signups on signups.date = days.date::date
            left join posts on posts.date = days.date::date
            left join active_user_counts on active_user_counts.seen_at = days.date::date
            left join stars on stars.date = days.date::date
            left join follows on follows.date = days.date::date
            order by days.date
            SQL, [User::botGitHubIdsArray(), $from, $until]);

        $failedJobs = DB::connection(config()->string('queue.failed.database'))
            ->table(config()->string('queue.failed.table'))
            ->where('failed_at', '>=', $from)
            ->where('failed_at', '<', $until)
            ->selectRaw('failed_at::date::text as date, count(*) as count')
            ->groupByRaw('1')
            ->get()
            ->mapInto(Fluent::class)
            ->mapWithKeys(fn (Fluent $day): array => [$day->string('date')->value() => $day->integer('count')]);

        return array_map(fn (object $row): array => [
            'date' => $row->date,
            'signups' => (int) $row->signups,
            'commits' => (int) $row->commits,
            'replies' => (int) $row->replies,
            'active_users' => (int) $row->active_users,
            'stars' => (int) $row->stars,
            'follows' => (int) $row->follows,
            'failed_jobs' => $failedJobs->get($row->date, 0),
        ], $rows);
    }
}
