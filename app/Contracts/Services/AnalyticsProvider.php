<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Carbon\CarbonImmutable;

/**
 * The site's visitors and the custom events their browsers send, counted without cookies by a privacy-first analytics
 * service. Every method is null when the service didn't answer.
 *
 * @phpstan-type TrafficTotals array{visitors: int, pageviews: int, avg_duration: int, bounce_rate: float}
 * @phpstan-type TrafficPoint array{date: string, visitors: int, pageviews: int, avg_duration: int, bounce_rate: float}
 * @phpstan-type TrafficBreakdown array{name: string, visitors: int, pageviews: int}
 * @phpstan-type TrafficFilter 'page'|'referrer'|'country'|'device'|'browser'
 * @phpstan-type TrafficReport array{
 *     current: int,
 *     totals: TrafficTotals,
 *     timeline: array<int, TrafficPoint>,
 *     breakdowns: array<string, array<int, TrafficBreakdown>>
 * }
 */
interface AnalyticsProvider
{
    /**
     * The visitors on the site right now, and the totals, the totals of each UTC hour of a single day or each UTC day of
     * a longer range, and the top ten pages, referrers, countries, devices and browsers in the range, of the visits
     * matching every filter. An empty name or filter is a direct visit or an unknown value. The bounce rate is a
     * fraction of visits.
     *
     * @param  array<TrafficFilter, string>  $filters
     * @return TrafficReport|null
     */
    public function traffic(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): ?array;

    /**
     * The people who sent the event in the range.
     */
    public function eventVisitors(CarbonImmutable $from, CarbonImmutable $to, string $event): ?int;

    /**
     * The people who sent the event on each UTC day of the range that has any, by date.
     *
     * @return array<string, int>|null
     */
    public function eventVisitorsByDay(CarbonImmutable $from, CarbonImmutable $to, string $event): ?array;

    /**
     * The people who sent the event in the range for each value of a field, device_type say, most first. An empty name
     * is an unknown value.
     *
     * @return array<string, int>|null
     */
    public function eventVisitorsBy(CarbonImmutable $from, CarbonImmutable $to, string $event, string $field): ?array;
}
