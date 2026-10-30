<?php

declare(strict_types=1);

use App\Services\AnalyticsProviders\FathomAnalyticsProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00'));
});

it('reads current visitors, totals, and the top pages, referrers, countries, devices, and browsers in the range', function (): void {
    Http::fake([
        'api.usefathom.com/v1/current_visitors*' => Http::response(['total' => 7]),
        'api.usefathom.com/v1/aggregations*' => fn (Request $request) => Http::response(match ($request->data()['field_grouping'] ?? null) {
            null => [['uniques' => '600', 'pageviews' => '1500', 'avg_duration' => '42', 'bounce_rate' => '0.25']],
            'pathname' => [['pathname' => '/', 'uniques' => '500', 'pageviews' => '900'], ['pathname' => '/about', 'uniques' => '80', 'pageviews' => '100']],
            'referrer_hostname' => [['referrer_hostname' => '', 'uniques' => '300', 'pageviews' => '700']],
            default => [],
        }),
    ]);

    $traffic = new FathomAnalyticsProvider()->traffic(CarbonImmutable::parse('2026-10-12'), CarbonImmutable::parse('2026-10-18'));

    expect($traffic)->toBe([
        'current' => 7,
        'totals' => ['visitors' => 600, 'pageviews' => 1500, 'avg_duration' => 42, 'bounce_rate' => 0.25],
        'breakdowns' => [
            'pages' => [['name' => '/', 'visitors' => 500, 'pageviews' => 900], ['name' => '/about', 'visitors' => 80, 'pageviews' => 100]],
            'referrers' => [['name' => '', 'visitors' => 300, 'pageviews' => 700]],
            'countries' => [],
            'devices' => [],
            'browsers' => [],
        ],
    ]);

    Http::assertSentCount(7);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token')
        && ($request->data()['field_grouping'] ?? null) === 'country_code'
        && $request->data()['entity_id'] === 'SITE'
        && $request->data()['date_from'] === '2026-10-12 00:00:00'
        && $request->data()['date_to'] === '2026-10-18 12:00:00');
});

it('caches current visitors for a minute and each range for five', function (): void {
    Http::fake([
        'api.usefathom.com/v1/current_visitors*' => Http::response(['total' => 7]),
        'api.usefathom.com/v1/aggregations*' => Http::response([]),
    ]);

    $provider = new FathomAnalyticsProvider();
    $from = CarbonImmutable::parse('2026-10-01');
    $to = CarbonImmutable::parse('2026-10-10');

    $provider->traffic($from, $to);
    $provider->traffic($from, $to);

    Http::assertSentCount(7);

    $this->travel(61)->seconds();
    $provider->traffic($from, $to);

    Http::assertSentCount(8);

    $this->travel(240)->seconds();
    $provider->traffic($from, $to);

    Http::assertSentCount(15);

    $provider->traffic($from, $to->addDay());

    Http::assertSentCount(21);
});

it('counts no traffic as zero', function (): void {
    Http::fake([
        'api.usefathom.com/v1/current_visitors*' => Http::response(['total' => 0]),
        'api.usefathom.com/v1/aggregations*' => Http::response([]),
    ]);

    expect(new FathomAnalyticsProvider()->traffic(today()->toImmutable(), today()->toImmutable()))->toBe([
        'current' => 0,
        'totals' => ['visitors' => 0, 'pageviews' => 0, 'avg_duration' => 0, 'bounce_rate' => 0.0],
        'breakdowns' => ['pages' => [], 'referrers' => [], 'countries' => [], 'devices' => [], 'browsers' => []],
    ]);
});

it('keeps the traffic report available when the cached visitor count is a numeric string', function (string $cachedCount, int $expected): void {
    Cache::put('operations:fathom-current', $cachedCount, 60);
    Http::fake(['api.usefathom.com/v1/aggregations*' => Http::response([])]);

    expect(new FathomAnalyticsProvider()->traffic(CarbonImmutable::parse('2026-10-12'), CarbonImmutable::parse('2026-10-18')))->toBe([
        'current' => $expected,
        'totals' => ['visitors' => 0, 'pageviews' => 0, 'avg_duration' => 0, 'bounce_rate' => 0.0],
        'breakdowns' => ['pages' => [], 'referrers' => [], 'countries' => [], 'devices' => [], 'browsers' => []],
    ]);

    Http::assertSentCount(6);
})->with([
    ['0', 0],
    ['7', 7],
]);

it('is null when Fathom cannot be reached or answers with something unexpected', function (mixed $response): void {
    Http::fake(['api.usefathom.com/*' => $response]);

    expect(new FathomAnalyticsProvider()->traffic(today()->toImmutable(), today()->toImmutable()))->toBeNull();
})->with([
    'an error' => fn () => Http::response(['error' => 'Unauthenticated'], 401),
    'rate limited' => fn () => Http::response([], 429),
    'no total' => fn () => Http::response(['message' => 'nope']),
    'a non-numeric total' => fn () => Http::response(['total' => 'nope']),
    'a timeout' => fn () => Http::failedConnection(),
]);

it('counts the people who sent an event, cached for five minutes', function (): void {
    Http::fake(['api.usefathom.com/v1/aggregations*' => Http::response([['unique_conversions' => '7']])]);

    $provider = new FathomAnalyticsProvider();
    $from = CarbonImmutable::parse('2026-10-01');
    $to = CarbonImmutable::parse('2026-10-18');

    expect($provider->eventVisitors($from, $to, 'signup'))->toBe(7)
        ->and($provider->eventVisitors($from, $to, 'signup'))->toBe(7);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.usefathom.com/v1/aggregations?entity=event&site_id=SITE'
        .'&entity_name=signup&date_from=2026-10-01%2000%3A00%3A00&date_to=2026-10-18%2012%3A00%3A00&timezone=UTC&aggregates=unique_conversions');
});

it('returns an integer when the cached event count is a numeric string', function (string $cachedCount, int $expected): void {
    Cache::put('operations:fathom-event:signup:2026-10-01:2026-10-18', $cachedCount, 300);
    Http::fake(['api.usefathom.com/v1/aggregations*' => Http::response([])]);

    expect(new FathomAnalyticsProvider()->eventVisitors(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-18'), 'signup'))->toBe($expected);

    Http::assertNothingSent();
})->with([
    ['0', 0],
    ['7', 7],
]);

it('counts the people who sent an event on each day', function (): void {
    Http::fake(['api.usefathom.com/v1/aggregations*' => Http::response([
        ['unique_conversions' => '7', 'date' => '2026-10-16'],
        ['unique_conversions' => '9', 'date' => '2026-10-18'],
    ])]);

    expect(new FathomAnalyticsProvider()->eventVisitorsByDay(CarbonImmutable::parse('2026-10-16'), CarbonImmutable::parse('2026-10-18'), 'signup'))
        ->toBe(['2026-10-16' => 7, '2026-10-18' => 9]);

    Http::assertSent(fn (Request $request): bool => $request->data()['date_grouping'] === 'day');
});

it('counts the people who sent an event for each value of a field, most first', function (): void {
    Http::fake(['api.usefathom.com/v1/aggregations*' => Http::response([
        ['unique_conversions' => '9', 'device_type' => 'Phone'],
        ['unique_conversions' => '7', 'device_type' => 'Desktop'],
    ])]);

    expect(new FathomAnalyticsProvider()->eventVisitorsBy(CarbonImmutable::parse('2026-10-16'), CarbonImmutable::parse('2026-10-18'), 'signup', 'device_type'))
        ->toBe(['Phone' => 9, 'Desktop' => 7]);

    Http::assertSent(fn (Request $request): bool => $request->data()['field_grouping'] === 'device_type'
        && $request->data()['sort_by'] === 'unique_conversions:desc');
});

it('stops asking Fathom for the rest of the request once it cannot be reached', function (): void {
    $from = CarbonImmutable::parse('2026-10-01');
    $to = CarbonImmutable::parse('2026-10-18');

    Http::fake(['api.usefathom.com/*' => Http::response(status: 500)]);

    expect(new FathomAnalyticsProvider()->eventVisitors($from, $to, 'signup'))->toBeNull()
        ->and(new FathomAnalyticsProvider()->eventVisitors($from, $to, 'guest-visit'))->toBeNull();

    Http::assertSentCount(1);
});
