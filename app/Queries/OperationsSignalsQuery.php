<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Number;

/**
 * @phpstan-type SignalStatus 'ok'|'warn'|'fail'
 * @phpstan-type Signal array{key: string, area: 'infrastructure'|'queues'|'moderation'|'growth'|'engagement', status: SignalStatus, title: string, detail: string, href: string|null}
 */
final readonly class OperationsSignalsQuery
{
    private const int QUEUE_WARN_SECONDS = 5 * 60;

    private const int QUEUE_FAIL_SECONDS = 30 * 60;

    /**
     * Failed jobs fail the check once there are at least this many in a day, and this many times the day before.
     */
    private const int FAILED_JOBS_FAIL_COUNT = 10;

    private const int FAILED_JOBS_FAIL_RATIO = 3;

    private const int LINK_PREVIEW_BACKLOG = 50;

    private const int MODERATION_WARN_SECONDS = 3600;

    private const int MODERATION_FAIL_SECONDS = 6 * 3600;

    /**
     * Yesterday is compared with the 14 days before it, in standard deviations, once at least 7 of them have counted
     * something and they averaged enough for one day to mean anything.
     */
    private const int BASELINE_DAYS = 14;

    private const int MIN_BASELINE_DAYS = 7;

    private const float MIN_BASELINE_MEAN = 5.0;

    private const float Z_WARN = -1.0;

    private const float Z_FAIL = -2.0;

    /**
     * Rates are compared in percentage points, the last 7 days with the 7 before, once both have enough people.
     */
    private const int MIN_RATE_SAMPLE = 30;

    private const float RATE_WARN_DROP = 5.0;

    private const float RATE_FAIL_DROP = 10.0;

    private const array RANK = ['fail' => 0, 'warn' => 1, 'ok' => 2];

    public function __construct(
        private ConnectivityQuery $connectivity,
        private QueueBacklogQuery $queues,
        private FailedJobTrendQuery $failedJobs,
        private LinkPreviewHealthQuery $linkPreviews,
        private ModerationBacklogQuery $moderation,
        private DailySeriesQuery $dailySeries,
        private ActivationByDayQuery $activation,
        private CommitResponsesQuery $responses,
    ) {
        //
    }

    /**
     * Where the app is failing and where it is fine, the failing first.
     *
     * @return list<Signal>
     */
    public function get(): array
    {
        $today = CarbonImmutable::today();
        $days = $this->dailySeries->get($today->subDays(self::BASELINE_DAYS + 1), $today->subDay());
        $moderation = $this->moderation->get();

        $signals = [
            $this->connectivity(),
            $this->queueBacklog(),
            $this->failedJobs(),
            $this->linkPreviews(),
            $this->moderationBacklog($moderation['pending'], $moderation['oldest_pending_age']),
            $this->openReports($moderation['open_reports']),
            $this->trend('signups', 'Signups', array_column($days, 'signups'), route('site-activity.show', absolute: false)),
            $this->trend('active_users', 'Active users', array_column($days, 'active_users'), route('product-metrics.show', absolute: false)),
            $this->activation($today),
            $this->responses($today),
        ];

        usort($signals, fn (array $a, array $b): int => self::RANK[$a['status']] <=> self::RANK[$b['status']]);

        return $signals;
    }

    /**
     * @return Signal
     */
    private function connectivity(): array
    {
        $reaches = $this->connectivity->get();
        $down = array_keys(array_filter($reaches, fn (bool $reached): bool => ! $reached));

        return [
            'key' => 'connectivity',
            'area' => 'infrastructure',
            'status' => $down === [] ? 'ok' : 'fail',
            'title' => $down === [] ? 'Database and cache reachable' : ucfirst(implode(' and ', $down)).' unreachable',
            'detail' => collect($reaches)->map(fn (bool $reached, string $name): string => $name.' '.($reached ? 'ok' : 'down'))->implode(' · '),
            'href' => route('system-health.show', absolute: false),
        ];
    }

    /**
     * @return Signal
     */
    private function queueBacklog(): array
    {
        $queues = $this->queues->get();
        $oldest = collect($queues)->sortByDesc('oldest_job_age')->first();
        $age = $oldest['oldest_job_age'] ?? null;

        return [
            'key' => 'queue_backlog',
            'area' => 'queues',
            'status' => match (true) {
                $age !== null && $age >= self::QUEUE_FAIL_SECONDS => 'fail',
                $age !== null && $age >= self::QUEUE_WARN_SECONDS => 'warn',
                default => 'ok',
            },
            'title' => $age === null ? 'Queues empty' : "Oldest job waiting {$this->duration($age)}",
            'detail' => Number::format(array_sum(array_column($queues, 'size'))).' queued'.($oldest === null || $age === null ? '' : " · oldest on {$oldest['queue']}"),
            'href' => route('system-health.show', absolute: false),
        ];
    }

    /**
     * @return Signal
     */
    private function failedJobs(): array
    {
        $trend = $this->failedJobs->get();
        $count = $trend['last_24h'];
        $previous = $trend['previous_24h'];

        return [
            'key' => 'failed_jobs',
            'area' => 'queues',
            'status' => match (true) {
                $count >= self::FAILED_JOBS_FAIL_COUNT && $count >= self::FAILED_JOBS_FAIL_RATIO * $previous => 'fail',
                $count > 0 => 'warn',
                default => 'ok',
            },
            'title' => $count === 0 ? 'No failed jobs in 24h' : Number::format($count).' failed '.str('job')->plural($count).' in 24h',
            'detail' => Number::format($count).' in 24h · '.Number::format($previous).' the 24h before',
            'href' => route('system-health.show', absolute: false),
        ];
    }

    /**
     * @return Signal
     */
    private function linkPreviews(): array
    {
        $previews = $this->linkPreviews->get();

        return [
            'key' => 'link_previews',
            'area' => 'infrastructure',
            'status' => $previews['pending'] >= self::LINK_PREVIEW_BACKLOG ? 'warn' : 'ok',
            'title' => $previews['pending'] >= self::LINK_PREVIEW_BACKLOG ? 'Link previews backing up' : 'Link previews keeping up',
            'detail' => Number::format($previews['pending']).' pending · '.Number::format($previews['failed']).' failed in 24h',
            'href' => route('system-health.show', absolute: false),
        ];
    }

    /**
     * @return Signal
     */
    private function moderationBacklog(int $pending, ?int $age): array
    {
        return [
            'key' => 'moderation_backlog',
            'area' => 'moderation',
            'status' => match (true) {
                $age !== null && $age >= self::MODERATION_FAIL_SECONDS => 'fail',
                $age !== null && $age >= self::MODERATION_WARN_SECONDS => 'warn',
                default => 'ok',
            },
            'title' => $pending === 0 ? 'Moderation queue empty' : Number::format($pending).' '.str('commit')->plural($pending).' awaiting review',
            'detail' => $age === null ? '0 pending' : Number::format($pending)." pending · oldest {$this->duration($age)}",
            'href' => route('moderation.show', absolute: false),
        ];
    }

    /**
     * @return Signal
     */
    private function openReports(int $reports): array
    {
        return [
            'key' => 'open_reports',
            'area' => 'moderation',
            'status' => $reports > 0 ? 'warn' : 'ok',
            'title' => $reports === 0 ? 'No open reports' : Number::format($reports).' open '.str('report')->plural($reports),
            'detail' => Number::format($reports).' open',
            'href' => route('reported-commit.index', absolute: false),
        ];
    }

    /**
     * Yesterday against the days before it, the series' first days left out until it counted something. Small counts
     * vary a lot from day to day, so the spread is never taken as less than a count's natural one, its square root.
     *
     * @param  'signups'|'active_users'  $key
     * @param  list<int>  $values
     * @return Signal
     */
    private function trend(string $key, string $title, array $values, string $href): array
    {
        $yesterday = (int) array_pop($values);
        $baseline = array_values(collect($values)->skipUntil(fn (int $value): bool => $value > 0)->all());
        $days = count($baseline);
        $mean = $days === 0 ? 0.0 : array_sum($baseline) / $days;

        if ($days < self::MIN_BASELINE_DAYS || $mean < self::MIN_BASELINE_MEAN) {
            return [
                'key' => $key,
                'area' => 'growth',
                'status' => 'ok',
                'title' => "{$title} yesterday: not enough data yet",
                'detail' => Number::format($yesterday).' yesterday · '.$days.' days of history',
                'href' => $href,
            ];
        }

        $deviation = max(sqrt(array_sum(array_map(fn (int $value): float => ($value - $mean) ** 2, $baseline)) / ($days - 1)), sqrt($mean));
        $z = ($yesterday - $mean) / $deviation;

        return [
            'key' => $key,
            'area' => 'growth',
            'status' => match (true) {
                $z <= self::Z_FAIL => 'fail',
                $z <= self::Z_WARN => 'warn',
                default => 'ok',
            },
            'title' => match (true) {
                $z <= self::Z_FAIL => "{$title} yesterday far below normal",
                $z <= self::Z_WARN => "{$title} yesterday below normal",
                default => "{$title} yesterday normal",
            },
            'detail' => sprintf('%s yesterday · %s ± %s over %dd · z %+.1f', Number::format($yesterday), Number::format($mean, 1), Number::format($deviation, 1), $days, $z),
            'href' => $href,
        ];
    }

    /**
     * @return Signal
     */
    private function activation(CarbonImmutable $today): array
    {
        $rate = function (CarbonImmutable $from, CarbonImmutable $to): array {
            $days = $this->activation->get($from, $to);

            return [array_sum(array_column($days, 'activated')), array_sum(array_column($days, 'eligible'))];
        };

        return $this->rate(
            'activation',
            'Activation',
            $rate($today->subDays(6), $today),
            $rate($today->subDays(13), $today->subDays(7)),
            'signups',
            route('product-metrics.show', absolute: false),
        );
    }

    /**
     * @return Signal
     */
    private function responses(CarbonImmutable $today): array
    {
        $rate = function (CarbonImmutable $from, CarbonImmutable $to): array {
            $responses = $this->responses->get($from, $to);

            return [$responses['answered'], $responses['commits']];
        };

        return $this->rate(
            'commits_answered',
            'Commits answered',
            $rate($today->subDays(6), $today),
            $rate($today->subDays(13), $today->subDays(7)),
            'commits',
            route('product-metrics.show', absolute: false),
        );
    }

    /**
     * @param  array{int, int}  $current
     * @param  array{int, int}  $previous
     * @return Signal
     */
    private function rate(string $key, string $title, array $current, array $previous, string $unit, string $href): array
    {
        [$hits, $total] = $current;
        [$previousHits, $previousTotal] = $previous;

        if ($total < self::MIN_RATE_SAMPLE || $previousTotal < self::MIN_RATE_SAMPLE) {
            return [
                'key' => $key,
                'area' => 'engagement',
                'status' => 'ok',
                'title' => "{$title}: not enough data yet",
                'detail' => Number::format($total)." {$unit} in 7d · ".Number::format($previousTotal).' the 7d before',
                'href' => $href,
            ];
        }

        $rate = $hits / $total * 100;
        $previousRate = $previousHits / $previousTotal * 100;
        $drop = $previousRate - $rate;

        return [
            'key' => $key,
            'area' => 'engagement',
            'status' => match (true) {
                $drop >= self::RATE_FAIL_DROP => 'fail',
                $drop >= self::RATE_WARN_DROP => 'warn',
                default => 'ok',
            },
            'title' => $drop >= self::RATE_WARN_DROP ? "{$title} down" : "{$title} holding",
            'detail' => sprintf('%s%% in 7d · %s%% the 7d before · %s %s', Number::format($rate, 1), Number::format($previousRate, 1), Number::format($total), $unit),
            'href' => $href,
        ];
    }

    /**
     * Seconds stop mattering once something has waited an hour.
     */
    private function duration(int $seconds): string
    {
        return CarbonInterval::seconds($seconds >= 3600 ? intdiv($seconds, 60) * 60 : $seconds)->cascade()->forHumans(['short' => true, 'parts' => 2]);
    }
}
