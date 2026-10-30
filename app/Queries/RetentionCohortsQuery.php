<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type RetentionCell array{day: int, eligible: int, retained: int, backfilled: int}
 * @phpstan-type RetentionCohort array{week: string, signups: int, days: list<RetentionCell>}
 * @phpstan-type RetentionCohorts array{cohorts: list<RetentionCohort>, curve: list<RetentionCell>}
 */
final readonly class RetentionCohortsQuery
{
    /**
     * The days since signing up the cohort table shows. The curve shows every day up to the last.
     */
    public const array DAYS = [1, 3, 7, 14, 30];

    /**
     * The signups of the range by week, Monday first, oldest first, with how many were seen again exactly N days after
     * the day they signed up, for each day the table shows, and the curve of all of them pooled, for every day up to
     * the last. Each count only holds the signups whose day N is over, so a cell of a week still reaching it holds part
     * of the week, and how many of those days came before user_active_days was recorded, as they are backfilled and a
     * floor. Bots are left out.
     *
     * @return RetentionCohorts
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<object{week: string, day: int, signups: int, eligible: int, retained: int, backfilled: int}> $rows */
        $rows = DB::select(<<<'SQL'
            with cohort as (
                select id, created_at::date as signed_up_at, date_trunc('week', created_at)::date as week from users
                where github_id <> all(?::text[]) and created_at >= ? and created_at < ?
            )
            select c.week, n.day, count(*) as signups,
                count(*) filter (where c.signed_up_at + n.day < bounds.today) as eligible,
                count(a.user_id) filter (where c.signed_up_at + n.day < bounds.today) as retained,
                count(*) filter (where c.signed_up_at + n.day < bounds.today and c.signed_up_at + n.day < bounds.recorded_since) as backfilled
            from cohort c
            cross join generate_series(1, ?::int) as n(day)
            cross join (select ?::date as today, ?::date as recorded_since) as bounds
            left join user_active_days a on a.user_id = c.id and a.seen_at = c.signed_up_at + n.day
            group by 1, 2
            order by 1, 2
            SQL, [
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
            max(self::DAYS),
            CarbonImmutable::today()->toDateString(),
            RetentionByDayQuery::RECORDED_SINCE,
        ]);

        $cell = fn (int $day, int $eligible, int $retained, int $backfilled): array => [
            'day' => $day,
            'eligible' => $eligible,
            'retained' => $retained,
            'backfilled' => $backfilled,
        ];

        $cohorts = collect($rows)->groupBy('week')->map(fn ($days, string $week): array => [
            'week' => $week,
            'signups' => (int) $days->first()->signups,
            'days' => $days
                ->filter(fn (object $row): bool => in_array((int) $row->day, self::DAYS, true))
                ->map(fn (object $row): array => $cell((int) $row->day, (int) $row->eligible, (int) $row->retained, (int) $row->backfilled))
                ->values()
                ->all(),
        ]);

        $curve = collect($rows)->groupBy('day')
            ->map(fn ($weeks, int $day): array => $cell(
                $day,
                (int) $weeks->sum('eligible'),
                (int) $weeks->sum('retained'),
                (int) $weeks->sum('backfilled'),
            ))
            ->filter(fn (array $point): bool => $point['eligible'] > 0);

        return ['cohorts' => $cohorts->values()->all(), 'curve' => $curve->values()->all()];
    }
}
