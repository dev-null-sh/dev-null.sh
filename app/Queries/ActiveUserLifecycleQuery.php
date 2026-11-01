<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type LifecycleDay array{date: string, new: int, regular: int, back: int, lost: int|null, backfilled: bool}
 */
final readonly class ActiveUserLifecycleQuery
{
    /**
     * How many days without a visit make someone gone: back when they return after that, lost on the day they reach it.
     */
    public const int GAP_DAYS = 7;

    /**
     * Each day of the range up to today, oldest first, with the people seen that day split by where they come from: new,
     * as they signed up that day, regular, as they were seen or signed up in the GAP_DAYS before, or back after a longer
     * gap. Lost counts the people seen GAP_DAYS before and not since, so a return shows on the day it happens, whenever
     * they signed up, and a loss on the day it's sure. Today is still counting, so it has no lost yet. A day is
     * backfilled when the days it looks back on came before user_active_days was recorded, as they miss the days people
     * only read, which makes regulars look back. Bots are left out.
     *
     * @return list<LifecycleDay>
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = CarbonImmutable::today();

        if ($from->isAfter($today)) {
            return [];
        }

        /** @var list<object{date: string, new: int, regular: int, back: int, lost: int}> $rows */
        $rows = DB::select(<<<'SQL'
            with active as (
                select a.seen_at, u.created_at::date as signed_up_at,
                    lag(a.seen_at) over seen as previous,
                    lead(a.seen_at) over seen as next
                from user_active_days a
                join users u on u.id = a.user_id
                where u.github_id <> all(?::text[]) and a.seen_at >= ?::date - ?::int
                window seen as (partition by a.user_id order by a.seen_at)
            )
            select d.day::date as date, coalesce(s.new, 0) as new, coalesce(s.regular, 0) as regular,
                coalesce(s.back, 0) as back, coalesce(l.lost, 0) as lost
            from generate_series(?::date, ?::date, interval '1 day') as d(day)
            left join (
                select seen_at,
                    count(*) filter (where signed_up_at = seen_at) as new,
                    count(*) filter (where signed_up_at < seen_at and (signed_up_at >= seen_at - ?::int or previous >= seen_at - ?::int)) as regular,
                    count(*) filter (where signed_up_at < seen_at - ?::int and (previous is null or previous < seen_at - ?::int)) as back
                from active
                group by seen_at
            ) s on s.seen_at = d.day
            left join (
                select seen_at + ?::int as day, count(*) as lost
                from active
                where next is null or next > seen_at + ?::int
                group by 1
            ) l on l.day = d.day
            order by 1
            SQL, [
            User::botGitHubIdsArray(),
            $from->toDateString(),
            self::GAP_DAYS,
            $from->toDateString(),
            $to->min($today)->toDateString(),
            ...array_fill(0, 6, self::GAP_DAYS),
        ]);

        return array_map(fn (object $row): array => [
            'date' => $row->date,
            'new' => (int) $row->new,
            'regular' => (int) $row->regular,
            'back' => (int) $row->back,
            'lost' => $row->date === $today->toDateString() ? null : (int) $row->lost,
            'backfilled' => CarbonImmutable::parse($row->date)->subDays(self::GAP_DAYS)->toDateString() < RetentionByDayQuery::RECORDED_SINCE,
        ], $rows);
    }
}
