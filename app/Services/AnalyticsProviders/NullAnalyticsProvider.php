<?php

declare(strict_types=1);

namespace App\Services\AnalyticsProviders;

use App\Contracts\Services\AnalyticsProvider;
use Carbon\CarbonImmutable;

/**
 * A site nobody visits, so developing and testing never reach a third party.
 */
final readonly class NullAnalyticsProvider implements AnalyticsProvider
{
    public function traffic(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'current' => 0,
            'totals' => ['visitors' => 0, 'pageviews' => 0, 'avg_duration' => 0, 'bounce_rate' => 0.0],
            'breakdowns' => ['pages' => [], 'referrers' => [], 'countries' => [], 'devices' => [], 'browsers' => []],
        ];
    }

    public function eventVisitors(CarbonImmutable $from, CarbonImmutable $to, string $event): ?int
    {
        return null;
    }

    public function eventVisitorsByDay(CarbonImmutable $from, CarbonImmutable $to, string $event): ?array
    {
        return null;
    }

    public function eventVisitorsBy(CarbonImmutable $from, CarbonImmutable $to, string $event, string $field): ?array
    {
        return null;
    }
}
