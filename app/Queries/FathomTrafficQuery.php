<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;

final readonly class FathomTrafficQuery
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

    public function isConfigured(): bool
    {
        return filled(config('services.fathom.token')) && filled(config('services.fathom.site_id'));
    }

    /**
     * Every Fathom API call counts against the account's monthly pageviews and hourly rate limit, so current visitors
     * are cached for a minute and each range's report for five. A failed read is not cached, so the next refresh tries
     * again. Breakdowns are the top ten by visitors, and an empty name is a direct visit or an unknown value.
     *
     * @return array{current: int, totals: array{visitors: int, pageviews: int, avg_duration: int, bounce_rate: float}, breakdowns: array<string, array<int, array{name: string, visitors: int, pageviews: int}>>}|null
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        try {
            $current = Cache::remember('operations:fathom-current', 60, fn (): mixed => $this->request()
                ->get('current_visitors', ['site_id' => config('services.fathom.site_id')])
                ->throw()
                ->json('total'));

            if (! is_int($current)) {
                return null;
            }

            $report = Cache::remember("operations:fathom-report:{$from->toDateString()}:{$to->toDateString()}", 300, function () use ($from, $to): array {
                $totals = $this->aggregate($from, $to, ['aggregates' => 'uniques,pageviews,avg_duration,bounce_rate'])->fluent('0');

                return [
                    'totals' => [
                        'visitors' => $totals->integer('uniques'),
                        'pageviews' => $totals->integer('pageviews'),
                        'avg_duration' => $totals->integer('avg_duration'),
                        'bounce_rate' => $totals->float('bounce_rate'),
                    ],
                    'breakdowns' => collect(self::BREAKDOWNS)->map(fn (string $field): array => $this->breakdown($from, $to, $field))->all(),
                ];
            });
        } catch (HttpClientException) {
            return null;
        }

        return ['current' => $current, ...$report];
    }

    /**
     * The people who sent the event in the range, a custom event tracked in the browser, or null when Fathom isn't set
     * up or didn't answer. Cached for five minutes, like the report.
     */
    public function eventVisitors(CarbonImmutable $from, CarbonImmutable $to, string $event): ?int
    {
        return $this->count("event:{$event}:{$from->toDateString()}:{$to->toDateString()}", fn (): int => $this->aggregate($from, $to, [
            'aggregates' => 'unique_conversions',
        ], $event)->fluent('0')->integer('unique_conversions'));
    }

    /**
     * The visits that started on a page whose path matches the pattern, a regular expression that Fathom runs, or null
     * when Fathom isn't set up or didn't answer. Cached for five minutes, like the report.
     */
    public function entries(CarbonImmutable $from, CarbonImmutable $to, string $pattern): ?int
    {
        return $this->count('entries:'.hash('xxh128', $pattern).":{$from->toDateString()}:{$to->toDateString()}", fn (): int => $this->aggregate($from, $to, [
            'aggregates' => 'visits',
            'filters' => json_encode([['property' => 'entry_page', 'operator' => 'matching', 'value' => $pattern]], JSON_THROW_ON_ERROR),
        ])->fluent('0')->integer('visits'));
    }

    /**
     * Once Fathom fails to answer, the rest of the request stops asking it, so a slow Fathom is only waited on once.
     *
     * @param  Closure(): int  $count
     */
    private function count(string $key, Closure $count): ?int
    {
        if (! $this->isConfigured() || Context::hasHidden(self::UNAVAILABLE)) {
            return null;
        }

        try {
            return Cache::remember("operations:fathom-{$key}", 300, $count);
        } catch (HttpClientException) {
            Context::addHidden(self::UNAVAILABLE, true);

            return null;
        }
    }

    /**
     * @return array<int, array{name: string, visitors: int, pageviews: int}>
     */
    private function breakdown(CarbonImmutable $from, CarbonImmutable $to, string $field): array
    {
        return $this->aggregate($from, $to, ['aggregates' => 'uniques,pageviews', 'field_grouping' => $field, 'sort_by' => 'uniques:desc', 'limit' => 10])
            ->collect()
            ->filter(fn (mixed $row): bool => is_array($row))
            ->mapInto(Fluent::class)
            ->map(fn (Fluent $row): array => [
                'name' => $row->string($field)->value(),
                'visitors' => $row->integer('uniques'),
                'pageviews' => $row->integer('pageviews'),
            ])
            ->values()
            ->all();
    }

    /**
     * The range runs from the start of its first day to the end of its last, or now when that is today. It reports on
     * pageviews, or on the custom event named.
     *
     * @param  array<string, string|int>  $query
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, array $query, ?string $event = null): Response
    {
        return $this->request()
            ->get('aggregations', [
                ...$event === null
                    ? ['entity' => 'pageview', 'entity_id' => config('services.fathom.site_id')]
                    : ['entity' => 'event', 'site_id' => config('services.fathom.site_id'), 'entity_name' => $event],
                'date_from' => $from->utc()->startOfDay()->format('Y-m-d H:i:s'),
                'date_to' => $to->utc()->endOfDay()->min(now())->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
                ...$query,
            ])
            ->throw();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl('https://api.usefathom.com/v1')
            ->acceptJson()
            ->withToken(config()->string('services.fathom.token'))
            ->connectTimeout(3)
            ->timeout(5);
    }
}
