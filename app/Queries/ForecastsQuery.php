<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use App\Services\Forecast;
use Carbon\CarbonImmutable;

/**
 * @phpstan-import-type DailyPoint from DailySeriesQuery
 * @phpstan-import-type ForecastMethod from Forecast
 *
 * @phpstan-type ForecastMetric 'active_users'|'signups'|'commits'|'replies'
 * @phpstan-type ForecastSeries array{metric: ForecastMetric, method: ForecastMethod, trend: float, today: int, history: list<array{date: string, value: int}>, forecast: list<array{date: string, value: float, lower: float, upper: float}>}
 * @phpstan-type Milestone array{metric: 'users'|'commits', current: int, target: int, eta: string|null}
 * @phpstan-type MonthProjection array{metric: 'signups'|'commits', month: string, to_date: int, projected: float, lower: float, upper: float}
 */
final readonly class ForecastsQuery
{
    /**
     * Eight weeks of complete days, so the four weeks the forecast fits are there even once the leading days before a
     * series started are left out.
     */
    private const int HISTORY_DAYS = 56;

    private const int HORIZON = 14;

    private const int MILESTONE_HORIZON = 365;

    /**
     * @var list<ForecastMetric>
     */
    private const array METRICS = ['active_users', 'signups', 'commits', 'replies'];

    public function __construct(private DailySeriesQuery $dailySeries, private Forecast $forecast)
    {
        //
    }

    /**
     * Each metric's last eight weeks, from the first day it counted anything, and its next two weeks, today included.
     *
     * @return list<ForecastSeries>
     */
    public function get(): array
    {
        $days = $this->days();

        return array_map(fn (string $metric): array => $this->series($days, $metric, self::HORIZON), self::METRICS);
    }

    /**
     * The next round number the people and the top-level commits will reach, and the day the forecast reaches it, if it
     * does within a year. Bots are left out, and deleted commits count, as they do in the forecast.
     *
     * @return list<Milestone>
     */
    public function milestones(): array
    {
        $days = $this->days();
        $bots = User::query()->whereIn('github_id', User::BOT_GITHUB_IDS)->select('id');

        return [
            $this->milestone('users', User::query()->whereNotIn('github_id', User::BOT_GITHUB_IDS)->count(), $this->series($days, 'signups', self::MILESTONE_HORIZON)),
            $this->milestone('commits', Commit::withTrashed()->topLevel()->whereNotIn('user_id', $bots)->count(), $this->series($days, 'commits', self::MILESTONE_HORIZON)),
        ];
    }

    /**
     * The signups and top-level commits of the current UTC month so far, and where the forecast of its remaining days
     * takes them.
     *
     * @return list<MonthProjection>
     */
    public function month(): array
    {
        $days = $this->days();
        $today = CarbonImmutable::today();
        $remaining = max(1, $today->daysInMonth - $today->day + 1);

        return array_map(function (string $metric) use ($days, $today, $remaining): array {
            $series = $this->series($days, $metric, $remaining);
            $toDate = array_sum(array_column(array_filter($days, fn (array $day): bool => str_starts_with($day['date'], $today->format('Y-m'))), $metric));

            return [
                'metric' => $metric,
                'month' => $today->format('Y-m'),
                'to_date' => $toDate,
                'projected' => round($toDate + array_sum($this->increments($series, 'value')), 1),
                'lower' => round($toDate + array_sum($this->increments($series, 'lower')), 1),
                'upper' => round($toDate + array_sum($this->increments($series, 'upper')), 1),
            ];
        }, ['signups', 'commits']);
    }

    /**
     * @return non-empty-list<DailyPoint>
     */
    private function days(): array
    {
        return once(fn (): array => $this->dailySeries->get(CarbonImmutable::today()->subDays(self::HISTORY_DAYS), CarbonImmutable::today()));
    }

    /**
     * Today is still counting, so it is left out of the history the forecast is fitted to.
     *
     * @param  non-empty-list<DailyPoint>  $days
     * @param  ForecastMetric  $metric
     * @param  positive-int  $horizon
     * @return ForecastSeries
     */
    private function series(array $days, string $metric, int $horizon): array
    {
        $today = CarbonImmutable::today();
        $history = array_values(collect(array_slice($days, 0, -1))
            ->skipUntil(fn (array $day): bool => $day[$metric] > 0)
            ->map(fn (array $day): array => ['date' => $day['date'], 'value' => $day[$metric]])
            ->all());
        $forecast = $this->forecast->predict(array_map(fn (array $day): float => $day['value'], $history), $horizon);

        return [
            'metric' => $metric,
            'method' => $forecast['method'],
            'trend' => $forecast['trend'],
            'today' => array_last($days)[$metric],
            'history' => $history,
            'forecast' => array_map(fn (array $point, int $index): array => [
                'date' => $today->addDays($index)->toDateString(),
                ...$point,
            ], $forecast['points'], array_keys($forecast['points'])),
        ];
    }

    /**
     * @param  'users'|'commits'  $metric
     * @param  ForecastSeries  $series
     * @return Milestone
     */
    private function milestone(string $metric, int $current, array $series): array
    {
        $target = $this->target($current);
        $reached = (float) $current;

        foreach ($this->increments($series, 'value') as $index => $increment) {
            $reached += $increment;

            if ($reached >= $target) {
                return ['metric' => $metric, 'current' => $current, 'target' => $target, 'eta' => $series['forecast'][$index]['date']];
            }
        }

        return ['metric' => $metric, 'current' => $current, 'target' => $target, 'eta' => null];
    }

    /**
     * What each day of the forecast adds from now on. Today's forecast is for the whole day, so it only adds what it
     * has over today's count so far.
     *
     * @param  ForecastSeries  $series
     * @param  'value'|'lower'|'upper'  $key
     * @return list<float>
     */
    private function increments(array $series, string $key): array
    {
        return array_map(
            fn (array $point, int $index): float => $index === 0 ? max(0.0, $point[$key] - $series['today']) : $point[$key],
            $series['forecast'],
            array_keys($series['forecast']),
        );
    }

    /**
     * The first of 10, 25, 50, 100, 250, 500, and so on, above the current count.
     */
    private function target(int $current): int
    {
        for ($power = 10; ; $power *= 10) {
            foreach ([$power, $power * 5 / 2, $power * 5] as $target) {
                if ($target > $current) {
                    return (int) $target;
                }
            }
        }
    }
}
