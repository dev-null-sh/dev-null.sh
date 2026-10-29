<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type ActivationDay from ActivationByDayQuery
 *
 * @phpstan-type AcquisitionDay array{date: string, signups: int, visitors: int|null, converted: int|null, complete: bool}
 * @phpstan-type DeviceConversion array{device: string, visitors: int, tapped: int, converted: int}
 * @phpstan-type FunnelRates array{conversion: float|null, activation: float|null, retention: float|null}
 * @phpstan-type ProductFunnel array{
 *     visitors: int|null,
 *     signups: int,
 *     acquisition_by_day: list<AcquisitionDay>,
 *     conversion_by_device: list<DeviceConversion>|null,
 *     activated: int,
 *     activation_eligible: int,
 *     activation_by_day: list<array{date: string, activated: int, eligible: int, complete: bool}>,
 *     retained: int,
 *     retention_eligible: int,
 *     rates: FunnelRates,
 *     changes: FunnelRates
 * }
 */
final readonly class ProductFunnelQuery
{
    /**
     * Retention is measured from this day after signing up: day 1 while /dev/null is days old, day 14 later.
     */
    private const int RETENTION_DAY = 1;

    /**
     * The Fathom events the browser sends for a visit by someone not signed in, a tap on a link to sign in, and a
     * signup.
     */
    private const string GUEST_VISIT_EVENT = 'guest-visit';

    private const string SIGN_IN_TAP_EVENT = 'signin-click';

    private const string SIGNUP_EVENT = 'signup';

    public function __construct(private FathomTrafficQuery $traffic, private ActivationByDayQuery $activationByDay)
    {
        //
    }

    /**
     * The funnel of the people who signed up in the range, bots left out, as defined in PRODUCT_METRICS.md: visitors
     * not signed in, signups, activation and retention, each with its rate and its change in percentage
     * points, acquisition for each day, and activation for each signup day. Changes compare the last 7 days with the 7 before when the range is all
     * time, or the range with as many days before it otherwise, and are null until both periods are after the launch.
     * Rates are fractions, null when nothing counts.
     *
     * @return ProductFunnel
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to, bool $isAllTime): array
    {
        $today = CarbonImmutable::today();
        $trackedSince = DB::table('active_user_counts')->min('seen_at');
        $trackedSince = is_string($trackedSince) ? CarbonImmutable::parse($trackedSince) : null;

        $launchedOn = User::query()->whereNotIn('github_id', User::BOT_GITHUB_IDS)->min('created_at');
        $launchedOn = is_string($launchedOn) ? CarbonImmutable::parse($launchedOn)->startOfDay() : $today;

        $retention = $this->retention($from, $to, $trackedSince);

        $visitors = $this->traffic->eventVisitors($from, $to, self::GUEST_VISIT_EVENT);
        $activationByDay = $this->activationByDay->get($from, $to);
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
        $ratesBetween = function (CarbonImmutable $from, CarbonImmutable $to) use ($isAllTime, $trackedSince): array {
            $activation = $this->activationTotal($this->activationByDay->get($from, $to));
            $retention = $this->retention(
                $isAllTime ? $from->subDays(self::RETENTION_DAY) : $from,
                $isAllTime ? $to->subDays(self::RETENTION_DAY) : $to,
                $trackedSince,
            );

            return [
                'conversion' => $this->conversion($from, $to, $this->traffic->eventVisitors($from, $to, self::GUEST_VISIT_EVENT)),
                'activation' => $this->rate($activation['activated'], $activation['eligible']),
                'retention' => $this->rate($retention['retained'], $retention['eligible']),
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
            'acquisition_by_day' => $this->acquisitionByDay($from, $to),
            'conversion_by_device' => $this->conversionByDevice($from, $to),
            'activated' => $activation['activated'],
            'activation_eligible' => $activation['eligible'],
            'activation_by_day' => array_map(fn (array $day): array => [
                ...$day,
                'complete' => CarbonImmutable::parse($day['date'])->addDay()->addHours(12)->lessThanOrEqualTo(now()),
            ], $activationByDay),
            'retained' => $retention['retained'],
            'retention_eligible' => $retention['eligible'],
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
     * Signups from the database, and visitors not signed in and signups from Fathom for the conversion, for each day
     * that has any, oldest first. Fathom's are null when it isn't set up or didn't answer. Today is incomplete.
     *
     * @return list<AcquisitionDay>
     */
    private function acquisitionByDay(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<object{date: string, signups: int}> $rows */
        $rows = DB::select(<<<'SQL'
            select created_at::date as date, count(*) as signups from users
            where github_id <> all(?::text[]) and created_at >= ? and created_at < ?
            group by 1
            SQL, [
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
        ]);

        $signups = array_column($rows, 'signups', 'date');

        $visitors = $this->traffic->eventVisitorsByDay($from, $to, self::GUEST_VISIT_EVENT);
        $converted = $visitors === null ? null : $this->traffic->eventVisitorsByDay($from, $to, self::SIGNUP_EVENT);
        $dates = array_unique([...array_keys($signups), ...array_keys($visitors ?? [])]);
        sort($dates);

        return array_map(fn (string $date): array => [
            'date' => $date,
            'signups' => (int) ($signups[$date] ?? 0),
            'visitors' => $visitors === null ? null : $visitors[$date] ?? 0,
            'converted' => $converted === null ? null : $converted[$date] ?? 0,
            'complete' => $date < CarbonImmutable::today()->toDateString(),
        ], $dates);
    }

    /**
     * Visitors not signed in, taps to sign in and signups from Fathom for each type of device, most visitors first, so
     * a change in the mix of devices can be told apart from a change in how each converts, and a pitch that doesn't
     * convince from sign-in at GitHub that people give up on. Null when Fathom isn't set up or didn't answer.
     *
     * @return list<DeviceConversion>|null
     */
    private function conversionByDevice(CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        $visitors = $this->traffic->eventVisitorsBy($from, $to, self::GUEST_VISIT_EVENT, 'device_type');
        $tapped = $visitors === null ? null : $this->traffic->eventVisitorsBy($from, $to, self::SIGN_IN_TAP_EVENT, 'device_type');
        $converted = $tapped === null ? null : $this->traffic->eventVisitorsBy($from, $to, self::SIGNUP_EVENT, 'device_type');

        if ($visitors === null || $tapped === null || $converted === null) {
            return null;
        }

        return array_map(fn (string $device): array => [
            'device' => $device,
            'visitors' => $visitors[$device] ?? 0,
            'tapped' => $tapped[$device] ?? 0,
            'converted' => $converted[$device] ?? 0,
        ], array_keys($visitors + $tapped + $converted));
    }

    /**
     * @param  list<ActivationDay>  $days
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
     * Retained: seen again on the retention day after signing up, or later. Only the signups whose day is over are
     * eligible, so nobody counts as gone on the very day they may still come back, and only once that day falls on or
     * after the day last_seen_at started being tracked, since a visit before that left no trace.
     *
     * @return array{retained: int, eligible: int}
     */
    private function retention(CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $trackedSince): array
    {
        if (! $trackedSince instanceof CarbonImmutable) {
            return ['retained' => 0, 'eligible' => 0];
        }

        $day = self::RETENTION_DAY;

        /** @var object{retained: int, eligible: int} $row */
        $row = DB::selectOne(<<<SQL
            select
                count(*) filter (where created_at::date + {$day} between bounds.tracked_since and bounds.until) as eligible,
                count(*) filter (where created_at::date + {$day} between bounds.tracked_since and bounds.until and last_seen_at >= created_at::date + {$day}) as retained
            from users, (select ?::date as tracked_since, ?::date as until) as bounds
            where github_id <> all(?::text[]) and created_at >= ? and created_at < ?
            SQL, [
            $trackedSince->toDateString(),
            CarbonImmutable::yesterday()->toDateString(),
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
        ]);

        return ['retained' => (int) $row->retained, 'eligible' => (int) $row->eligible];
    }

    private function rate(int $count, ?int $total): ?float
    {
        return $total === null || $total === 0 ? null : $count / $total;
    }
}
