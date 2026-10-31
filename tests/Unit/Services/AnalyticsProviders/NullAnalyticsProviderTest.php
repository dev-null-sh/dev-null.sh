<?php

declare(strict_types=1);

use App\Contracts\Services\AnalyticsProvider;
use App\Services\AnalyticsProviders\NullAnalyticsProvider;
use Carbon\CarbonImmutable;

it('is the analytics provider in testing', function (): void {
    expect(resolve(AnalyticsProvider::class))->toBeInstanceOf(NullAnalyticsProvider::class);
});

it('has a site nobody visits', function (): void {
    $provider = new NullAnalyticsProvider();
    $from = CarbonImmutable::parse('2026-10-01');
    $to = CarbonImmutable::parse('2026-10-18');

    expect($provider->traffic($from, $to))->toBe([
        'current' => 0,
        'totals' => ['visitors' => 0, 'pageviews' => 0, 'avg_duration' => 0, 'bounce_rate' => 0.0],
        'breakdowns' => ['pages' => [], 'referrers' => [], 'countries' => [], 'devices' => [], 'browsers' => []],
    ])
        ->and($provider->eventVisitors($from, $to, 'signup'))->toBeNull()
        ->and($provider->eventVisitorsByDay($from, $to, 'signup'))->toBeNull()
        ->and($provider->eventVisitorsBy($from, $to, 'signup', 'device_type'))->toBeNull();
});
