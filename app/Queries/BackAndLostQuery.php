<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type BackAndLostDay array{date: string, back: int, lost: int|null, lost_complete: bool, backfilled: bool}
 */
final readonly class BackAndLostQuery
{
    /**
     * How many days without a visit make someone gone: back when they return after that, lost from their first day away.
     */
    public const int GAP_DAYS = 7;

    /**
     * Each day of the range up to today, oldest first, with the people back that day, as they signed up and weren't seen
     * in the GAP_DAYS before, and the people lost that day, as it is the first of GAP_DAYS without a visit. So a return
     * shows on the day it happens, whenever they signed up, and a loss on the day it started, next to what may have
     * caused it. A loss is only sure once its GAP_DAYS are over: until then, lost counts the people not seen since, which
     * can only go down, and isn't complete. Today has no lost yet. A day is backfilled when the days it looks back on
     * came before user_active_days was recorded, as they miss the days people only read, which makes regulars look back.
     * Bots are left out.
     *
     * @return list<BackAndLostDay>
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = CarbonImmutable::today();

        /** @var list<object{date: string, back: int, lost: int}> $rows */
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
            select d.day::date as date, coalesce(b.back, 0) as back, coalesce(l.lost, 0) as lost
            from generate_series(?::date, ?::date, interval '1 day') as d(day)
            left join (
                select seen_at, count(*) as back
                from active
                where signed_up_at < seen_at - ?::int and (previous is null or previous < seen_at - ?::int)
                group by seen_at
            ) b on b.seen_at = d.day
            left join (
                select seen_at + 1 as day, count(*) as lost
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
            ...array_fill(0, 3, self::GAP_DAYS),
        ]);

        return array_map(fn (object $row): array => [
            'date' => $row->date,
            'back' => (int) $row->back,
            'lost' => $row->date === $today->toDateString() ? null : (int) $row->lost,
            'lost_complete' => CarbonImmutable::parse($row->date)->addDays(self::GAP_DAYS - 1)->isBefore($today),
            'backfilled' => CarbonImmutable::parse($row->date)->subDays(self::GAP_DAYS)->toDateString() < RetentionByDayQuery::RECORDED_SINCE,
        ], $rows);
    }
}
