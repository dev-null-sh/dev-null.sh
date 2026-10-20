<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

final readonly class ProductFunnelQuery
{
    /**
     * Retention is measured at the furthest of these days that the signups already got through, 14 when it can be.
     */
    private const array RETENTION_HORIZONS = [14, 7, 1];

    /**
     * The Fathom events the browser sends for a visit by someone not signed in, and for a signup.
     */
    private const string GUEST_VISIT_EVENT = 'guest-visit';

    private const string SIGNUP_EVENT = 'signup';

    public function __construct(private FathomTrafficQuery $traffic)
    {
        //
    }

    /**
     * The funnel of the people who signed up in the range, bots left out, as defined in PRODUCT_METRICS.md: visitors
     * not signed in, signups, activation and retention, each with its rate and its change in percentage
     * points, and activation for each signup day. Changes compare the last 7 days with the 7 before when the range is all
     * time, or the range with as many days before it otherwise, and are null until both periods are after the launch.
     * Rates are fractions, null when nothing counts.
     *
     * @return array{visitors: int|null, signups: int, activated: int, activation_eligible: int, activation_by_day: list<array{date: string, activated: int, eligible: int, complete: bool}>, retained: int, retention_eligible: int, retention_horizon: int|null, retention_full_on: string|null, rates: array{conversion: float|null, activation: float|null, retention: float|null}, changes: array{conversion: float|null, activation: float|null, retention: float|null}}
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to, bool $isAllTime): array
    {
        $today = CarbonImmutable::today();
        $trackedSince = DB::table('active_user_counts')->min('seen_at');
        $trackedSince = is_string($trackedSince) ? CarbonImmutable::parse($trackedSince) : null;

        $launchedOn = User::query()->whereNotIn('github_id', User::BOT_GITHUB_IDS)->min('created_at');
        $launchedOn = is_string($launchedOn) ? CarbonImmutable::parse($launchedOn)->startOfDay() : $today;

        $retentions = $this->retention($from, $to, $trackedSince);
        $horizon = collect(self::RETENTION_HORIZONS)->first(fn (int $days): bool => $retentions[$days]['eligible'] > 0);
        $retention = $horizon === null ? ['retained' => 0, 'eligible' => 0] : $retentions[$horizon];

        $visitors = $this->traffic->eventVisitors($from, $to, self::GUEST_VISIT_EVENT);
        $activationByDay = $this->activationByDay($from, $to);
        $activation = $this->activationTotal($activationByDay);

        $rates = [
            'conversion' => $this->conversion($from, $to, $visitors),
            'activation' => $this->rate($activation['activated'], $activation['eligible']),
            'retention' => $this->rate($retention['retained'], $retention['eligible']),
        ];

        [$currentFrom, $currentTo, $previousFrom, $previousTo] = $isAllTime
            ? [$today->subDays(6), $today, $today->subDays(13), $today->subDays(7)]
            : [$from, $to, $from->subDays((int) $from->diffInDays($to) + 1), $from->subDay()];

        // All time, retention compares the signups whose day N is in each period, a range compares its own signups...
        $ratesBetween = function (CarbonImmutable $from, CarbonImmutable $to) use ($isAllTime, $horizon, $trackedSince): array {
            $activation = $this->activationTotal($this->activationByDay($from, $to));
            $retention = $horizon === null ? null : $this->retention(
                $isAllTime ? $from->subDays($horizon) : $from,
                $isAllTime ? $to->subDays($horizon) : $to,
                $trackedSince,
            )[$horizon];

            return [
                'conversion' => $this->conversion($from, $to, $this->traffic->eventVisitors($from, $to, self::GUEST_VISIT_EVENT)),
                'activation' => $this->rate($activation['activated'], $activation['eligible']),
                'retention' => $retention === null ? null : $this->rate($retention['retained'], $retention['eligible']),
            ];
        };

        $periods = $previousFrom->greaterThanOrEqualTo($launchedOn)
            ? [$isAllTime ? $ratesBetween($currentFrom, $currentTo) : $rates, $ratesBetween($previousFrom, $previousTo)]
            : null;

        /** @param 'conversion'|'activation'|'retention' $rate */
        $change = fn (string $rate): ?float => $periods === null || $periods[0][$rate] === null || $periods[1][$rate] === null
            ? null
            : $periods[0][$rate] - $periods[1][$rate];

        return [
            'visitors' => $visitors,
            'signups' => User::query()
                ->whereNotIn('github_id', User::BOT_GITHUB_IDS)
                ->where('created_at', '>=', $from->startOfDay())
                ->where('created_at', '<', $to->addDay()->startOfDay())
                ->count(),
            'activated' => $activation['activated'],
            'activation_eligible' => $activation['eligible'],
            'activation_by_day' => array_map(fn (array $day): array => [
                ...$day,
                'complete' => CarbonImmutable::parse($day['date'])->addDay()->addHours(12)->lessThanOrEqualTo(now()),
            ], $activationByDay),
            'retained' => $retention['retained'],
            'retention_eligible' => $retention['eligible'],
            'retention_horizon' => $horizon,
            'retention_full_on' => $horizon === 14 ? null : $this->retentionFullOn($from, $to, $trackedSince),
            'rates' => $rates,
            'changes' => [
                'conversion' => $change('conversion'),
                'activation' => $change('activation'),
                'retention' => $change('retention'),
            ],
        ];
    }

    /**
     * Both sides come from Fathom, so ad blockers hide the same share of each and the rate between them holds, where
     * signups from the database over visitors from Fathom would overstate it.
     */
    private function conversion(CarbonImmutable $from, CarbonImmutable $to, ?int $visitors): ?float
    {
        $signups = $visitors === null ? null : $this->traffic->eventVisitors($from, $to, self::SIGNUP_EVENT);

        return $signups === null ? null : $this->rate($signups, $visitors);
    }

    /**
     * Activated, for each signup day that has eligible signups, oldest first: within 12 hours of signing up, someone
     * starred or saved commits by 3 or more other people, or posted and got a reply, a quote or a star from someone
     * else. Only the signups at least 12 hours old are eligible.
     *
     * @return list<array{date: string, activated: int, eligible: int}>
     */
    private function activationByDay(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $approved = CommitStatus::Approved->value;

        /** @var list<object{date: string, activated: int, eligible: int}> $rows */
        $rows = DB::select(<<<SQL
            with humans as not materialized (
                select id, created_at from users where github_id <> all(?::text[])
            ), newcomers as (
                select id, created_at, created_at + interval '12 hours' as until from humans
                where created_at >= ? and created_at < ? and created_at <= ?
            )
            select n.created_at::date as date, count(*) filter (where (
                select count(distinct c.user_id) from commits c
                join humans a on a.id = c.user_id
                where c.user_id <> n.id and c.id in (
                    select s.commit_id from stars s where s.user_id = n.id and s.created_at <= n.until
                    union all
                    select b.commit_id from bookmarks b where b.user_id = n.id and b.created_at <= n.until
                )
            ) >= 3 or exists (
                select 1 from commits p
                where p.user_id = n.id and p.created_at <= n.until and (
                    exists (
                        select 1 from stars s join humans h on h.id = s.user_id
                        where s.commit_id = p.id and s.user_id <> n.id and s.created_at <= n.until
                    ) or exists (
                        select 1 from commits r join humans h on h.id = r.user_id
                        where (r.parent_id = p.id or r.quoted_commit_id = p.id) and r.user_id <> n.id
                        and r.status = '{$approved}' and r.created_at <= n.until
                    )
                )
            )) as activated, count(*) as eligible
            from newcomers n
            group by 1
            order by 1
            SQL, [
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
            now()->subHours(12),
        ]);

        return array_map(fn (object $row): array => [
            'date' => $row->date,
            'activated' => (int) $row->activated,
            'eligible' => (int) $row->eligible,
        ], $rows);
    }

    /**
     * @param  list<array{date: string, activated: int, eligible: int}>  $days
     * @return array{activated: int, eligible: int}
     */
    private function activationTotal(array $days): array
    {
        return [
            'activated' => array_sum(array_column($days, 'activated')),
            'eligible' => array_sum(array_column($days, 'eligible')),
        ];
    }

    /**
     * Retained, at each horizon: seen again on that day after signing up, or later. Only the signups whose day is over
     * are eligible, so nobody counts as gone on the very day they may still come back, and only once that day falls on
     * or after the day last_seen_at started being tracked, since a visit before that left no trace.
     *
     * @return array<int, array{retained: int, eligible: int}>
     */
    private function retention(CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $trackedSince): array
    {
        if (! $trackedSince instanceof CarbonImmutable) {
            return collect(self::RETENTION_HORIZONS)->mapWithKeys(fn (int $days): array => [$days => ['retained' => 0, 'eligible' => 0]])->all();
        }

        $counts = collect(self::RETENTION_HORIZONS)->map(fn (int $days): string => <<<SQL
            count(*) filter (where created_at::date + {$days} between bounds.tracked_since and bounds.until) as eligible_{$days},
            count(*) filter (where created_at::date + {$days} between bounds.tracked_since and bounds.until and last_seen_at >= created_at::date + {$days}) as retained_{$days}
            SQL)->implode(",\n");

        $row = new Fluent((array) DB::selectOne(<<<SQL
            select {$counts}
            from users, (select ?::date as tracked_since, ?::date as until) as bounds
            where github_id <> all(?::text[]) and created_at >= ? and created_at < ?
            SQL, [
            $trackedSince->toDateString(),
            CarbonImmutable::yesterday()->toDateString(),
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
        ]));

        return collect(self::RETENTION_HORIZONS)->mapWithKeys(fn (int $days): array => [$days => [
            'retained' => $row->integer("retained_{$days}"),
            'eligible' => $row->integer("eligible_{$days}"),
        ]])->all();
    }

    /**
     * The day retention from day 14 becomes measurable for the range: the day after the first signup whose day 14 falls
     * after tracking started is over. Null when no signup of the range ever will.
     */
    private function retentionFullOn(CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $trackedSince): ?string
    {
        if (! $trackedSince instanceof CarbonImmutable) {
            return null;
        }

        $first = User::query()
            ->whereNotIn('github_id', User::BOT_GITHUB_IDS)
            ->where('created_at', '>=', $from->max($trackedSince->subDays(14))->startOfDay())
            ->where('created_at', '<', $to->addDay()->startOfDay())
            ->min('created_at');

        return is_string($first) ? CarbonImmutable::parse($first)->startOfDay()->addDays(15)->toDateString() : null;
    }

    private function rate(int $count, ?int $total): ?float
    {
        return $total === null || $total === 0 ? null : $count / $total;
    }
}
