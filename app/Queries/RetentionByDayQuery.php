<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type RetentionDay array{date: string, eligible: int, retained: int, backfilled: bool}
 */
final readonly class RetentionByDayQuery
{
    /**
     * The first day user_active_days was recorded as people were seen. The days before were backfilled from what
     * people did, which misses the days someone only read, so retention read from them is a floor.
     */
    public const string RECORDED_SINCE = '2026-10-30';

    /**
     * Retained on day N, for each signup day of the range whose day N is over, oldest first: the signups seen again
     * exactly N days after the day they signed up, out of all of them, bots left out. A day is backfilled when its day
     * N came before RECORDED_SINCE.
     *
     * @return list<RetentionDay>
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to, int $day): array
    {
        /** @var list<object{date: string, eligible: int, retained: int}> $rows */
        $rows = DB::select(<<<'SQL'
            select u.created_at::date as date, count(*) as eligible, count(a.user_id) as retained
            from users u
            left join user_active_days a on a.user_id = u.id and a.seen_at = u.created_at::date + ?::int
            where u.github_id <> all(?::text[]) and u.created_at >= ? and u.created_at < ?
            and u.created_at::date + ?::int < ?::date
            group by 1
            order by 1
            SQL, [
            $day,
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
            $day,
            CarbonImmutable::today()->toDateString(),
        ]);

        return array_map(fn (object $row): array => [
            'date' => $row->date,
            'eligible' => (int) $row->eligible,
            'retained' => (int) $row->retained,
            'backfilled' => CarbonImmutable::parse($row->date)->addDays($day)->toDateString() < self::RECORDED_SINCE,
        ], $rows);
    }
}
