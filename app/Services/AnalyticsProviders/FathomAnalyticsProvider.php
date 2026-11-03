<?php

declare(strict_types=1);

namespace App\Services\AnalyticsProviders;

use App\Contracts\Services\AnalyticsProvider;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;

/**
 * @phpstan-import-type TrafficTotals from AnalyticsProvider
 * @phpstan-import-type TrafficPoint from AnalyticsProvider
 * @phpstan-import-type TrafficBreakdown from AnalyticsProvider
 * @phpstan-import-type TrafficFilter from AnalyticsProvider
 * @phpstan-import-type TrafficReport from AnalyticsProvider
 */
final readonly class FathomAnalyticsProvider implements AnalyticsProvider
{
    /**
     * The hidden context key marking Fathom as not answering for the rest of the request.
     */
    private const string UNAVAILABLE = 'fathom-unavailable';

    /**
     * Each breakdown and the Fathom field it groups by.
     */
    private const array BREAKDOWNS = [
        'pages' => 'pathname',
        'referrers' => 'referrer_hostname',
        'countries' => 'country_code',
        'devices' => 'device_type',
        'browsers' => 'browser',
    ];

    /**
     * The aggregates of the totals, overall, over time and by breakdown row.
     */
    private const string TOTALS = 'uniques,pageviews,avg_duration,bounce_rate';

    /**
     * Each filter and the Fathom field it matches.
     */
    private const array FILTERS = [
        'page' => 'pathname',
        'referrer' => 'referrer_hostname',
        'country' => 'country_code',
        'device' => 'device_type',
        'browser' => 'browser',
    ];

    /**
     * Every Fathom API call counts against the account's monthly pageviews and hourly rate limit, so current visitors
     * are cached for a minute and each range's report for five. A failed read is not cached, so the next refresh tries
     * again.
     *
     * @param  array<TrafficFilter, string>  $filters
     * @return TrafficReport|null
     */
    public function traffic(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): ?array
    {
        try {
            $current = Cache::remember('operations:fathom-current', 60, fn (): mixed => $this->request()
                ->get('current_visitors', ['site_id' => $this->siteId()])
                ->throw()
                ->json('total'));

            if (! is_int($current) && (! is_string($current) || ! ctype_digit($current))) {
                return null;
            }

            $key = "operations:fathom-traffic:{$from->toDateString()}:{$to->toDateString()}:".hash('sha256', serialize($filters));
            $report = Cache::remember($key, 300, fn (): array => [
                'totals' => $this->totals($this->aggregate($from, $to, ['aggregates' => self::TOTALS], filters: $filters)->fluent('0')),
                'timeline' => $this->timeline($from, $to, $filters),
                'breakdowns' => collect(self::BREAKDOWNS)->map(fn (string $field): array => $this->breakdown($from, $to, $field, $filters))->all(),
            ]);
        } catch (HttpClientException) {
            return null;
        }

        return ['current' => (int) $current, ...$report];
    }

    /**
     * Cached for five minutes, like the report.
     */
    public function eventVisitors(CarbonImmutable $from, CarbonImmutable $to, string $event): ?int
    {
        return $this->count("event:{$event}:{$from->toDateString()}:{$to->toDateString()}", fn (): int => $this->aggregate($from, $to, [
            'aggregates' => 'unique_conversions',
        ], $event)->fluent('0')->integer('unique_conversions'));
    }

    /**
     * Cached for five minutes, like the report.
     *
     * @return array<string, int>|null
     */
    public function eventVisitorsByDay(CarbonImmutable $from, CarbonImmutable $to, string $event): ?array
    {
        return $this->count("event-by-day:{$event}:{$from->toDateString()}:{$to->toDateString()}", fn (): array => $this->aggregate($from, $to, [
            'aggregates' => 'unique_conversions',
            'date_grouping' => 'day',
            'sort_by' => 'timestamp:asc',
        ], $event)
            ->collect()
            ->filter(fn (mixed $row): bool => is_array($row))
            ->mapInto(Fluent::class)
            ->mapWithKeys(fn (Fluent $row): array => [$row->string('date')->substr(0, 10)->value() => $row->integer('unique_conversions')])
            ->all());
    }

    /**
     * Cached for five minutes, like the report.
     *
     * @return array<string, int>|null
     */
    public function eventVisitorsBy(CarbonImmutable $from, CarbonImmutable $to, string $event, string $field): ?array
    {
        return $this->count("event-by-{$field}:{$event}:{$from->toDateString()}:{$to->toDateString()}", fn (): array => $this->aggregate($from, $to, [
            'aggregates' => 'unique_conversions',
            'field_grouping' => $field,
            'sort_by' => 'unique_conversions:desc',
        ], $event)
            ->collect()
            ->filter(fn (mixed $row): bool => is_array($row))
            ->mapInto(Fluent::class)
            ->mapWithKeys(fn (Fluent $row): array => [$row->string($field)->value() => $row->integer('unique_conversions')])
            ->all());
    }

    /**
     * Once Fathom fails to answer, the rest of the request stops asking it, so a slow Fathom is only waited on once.
     *
     * @template T of int|array<string, int>
     *
     * @param  Closure(): T  $count
     * @return T|null
     */
    private function count(string $key, Closure $count): int|array|null
    {
        if (Context::hasHidden(self::UNAVAILABLE)) {
            return null;
        }

        try {
            $value = Cache::remember("operations:fathom-{$key}", 300, $count);

            return is_array($value) ? $value : (int) $value;
        } catch (HttpClientException) {
            Context::addHidden(self::UNAVAILABLE, true);

            return null;
        }
    }

    /**
     * Fathom gives the bounce rate as a percentage.
     *
     * @param  Fluent<string, mixed>  $row
     * @return TrafficTotals
     */
    private function totals(Fluent $row): array
    {
        return [
            'visitors' => $row->integer('uniques'),
            'pageviews' => $row->integer('pageviews'),
            'avg_duration' => $row->integer('avg_duration'),
            'bounce_rate' => $row->float('bounce_rate') / 100,
        ];
    }

    /**
     * Every hour of a single day, or every day of a longer range, up to now. Fathom leaves out the empty ones, so they
     * count zero.
     *
     * @param  array<TrafficFilter, string>  $filters
     * @return array<int, TrafficPoint>
     */
    private function timeline(CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $isHourly = $from->isSameDay($to);
        $rows = $this->aggregate($from, $to, [
            'aggregates' => self::TOTALS,
            'date_grouping' => $isHourly ? 'hour' : 'day',
            'sort_by' => 'timestamp:asc',
        ], filters: $filters)
            ->collect()
            ->filter(fn (mixed $row): bool => is_array($row))
            ->mapInto(Fluent::class)
            ->keyBy(fn (Fluent $row): string => $row->string('date')->value());

        return collect(CarbonPeriod::create($from->utc()->startOfDay(), $isHourly ? '1 hour' : '1 day', $to->utc()->endOfDay()->min(now()))->toArray())
            ->map(fn (CarbonInterface $at): array => [
                'date' => $isHourly ? $at->toIso8601ZuluString() : $at->toDateString(),
                ...$this->totals($rows->get($at->format($isHourly ? 'Y-m-d H:i:s' : 'Y-m-d'), new Fluent())),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<TrafficFilter, string>  $filters
     * @return array<int, TrafficBreakdown>
     */
    private function breakdown(CarbonImmutable $from, CarbonImmutable $to, string $field, array $filters): array
    {
        return $this->aggregate($from, $to, ['aggregates' => self::TOTALS, 'field_grouping' => $field, 'sort_by' => 'uniques:desc', 'limit' => 10], filters: $filters)
            ->collect()
            ->filter(fn (mixed $row): bool => is_array($row))
            ->mapInto(Fluent::class)
            ->map(fn (Fluent $row): array => ['name' => $row->string($field)->value(), ...$this->totals($row)])
            ->values()
            ->all();
    }

    /**
     * The range runs from the start of its first day to the end of its last, or now when that is today. It reports on
     * pageviews, or on the custom event named, matching every filter.
     *
     * @param  array<string, string|int>  $query
     * @param  array<TrafficFilter, string>  $filters
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, array $query, ?string $event = null, array $filters = []): Response
    {
        return $this->request()
            ->get('aggregations', [
                ...$event === null
                    ? ['entity' => 'pageview', 'entity_id' => $this->siteId()]
                    : ['entity' => 'event', 'site_id' => $this->siteId(), 'entity_name' => $event],
                'date_from' => $from->utc()->startOfDay()->format('Y-m-d H:i:s'),
                'date_to' => $to->utc()->endOfDay()->min(now())->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
                ...$filters === [] ? [] : ['filters' => json_encode(collect($filters)->map(fn (string $value, string $filter): array => [
                    'property' => self::FILTERS[$filter],
                    'operator' => 'is',
                    'value' => $value,
                ])->values()->all())],
                ...$query,
            ])
            ->throw();
    }

    private function siteId(): string
    {
        $siteId = config('services.fathom.site_id');
        assert(is_string($siteId));

        return $siteId;
    }

    private function request(): PendingRequest
    {
        $token = config('services.fathom.token');
        assert(is_string($token));

        return Http::baseUrl('https://api.usefathom.com/v1')
            ->acceptJson()
            ->withToken($token)
            ->connectTimeout(3)
            ->timeout(5);
    }
}
