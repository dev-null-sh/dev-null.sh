<?php

declare(strict_types=1);

use App\Jobs\EmbedCommit;
use App\Jobs\UnfurlLink;
use App\Queries\FailedJobTrendQuery;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
});

it('compares the failures of the last 24 hours with the 24 before', function (): void {
    failedJob(failedAt: now()->subHours(23));
    failedJob(failedAt: now()->subHours(25));
    failedJob(failedAt: now()->subHours(47));
    failedJob(failedAt: now()->subHours(49));

    expect(resolve(FailedJobTrendQuery::class)->get())
        ->last_24h->toBe(1)
        ->previous_24h->toBe(2);
});

it('counts the failures of each of the last 24 hours, up to the current one', function (): void {
    failedJob(failedAt: CarbonImmutable::parse('2026-10-20 12:10:00'));
    failedJob(failedAt: CarbonImmutable::parse('2026-10-20 12:20:00'));
    failedJob(failedAt: CarbonImmutable::parse('2026-10-19 13:00:00'));
    failedJob(failedAt: CarbonImmutable::parse('2026-10-19 12:59:59'));

    $hours = resolve(FailedJobTrendQuery::class)->get()['hours'];

    expect($hours)->toHaveCount(24)
        ->and($hours[0])->toBe(['at' => '2026-10-19T13:00:00+00:00', 'count' => 1])
        ->and($hours[1])->toBe(['at' => '2026-10-19T14:00:00+00:00', 'count' => 0])
        ->and($hours[23])->toBe(['at' => '2026-10-20T12:00:00+00:00', 'count' => 2]);
});

it('ranks the jobs that failed the most in the last 24 hours', function (): void {
    failedJob(failedAt: now()->subHours(20), payload: ['displayName' => EmbedCommit::class]);
    failedJob(failedAt: now()->subHours(3), payload: ['displayName' => UnfurlLink::class]);
    failedJob(failedAt: now()->subHour(), payload: ['displayName' => UnfurlLink::class]);
    failedJob(failedAt: now()->subHour(), payload: ['job' => 'Illuminate\Queue\CallQueuedHandler@call']);
    failedJob(failedAt: now()->subHours(25), payload: ['displayName' => EmbedCommit::class]);

    expect(resolve(FailedJobTrendQuery::class)->get()['by_job'])->toBe([
        ['name' => UnfurlLink::class, 'count' => 2, 'last_failed_at' => '2026-10-20T11:30:00+00:00'],
        ['name' => 'Illuminate\Queue\CallQueuedHandler@call', 'count' => 1, 'last_failed_at' => '2026-10-20T11:30:00+00:00'],
        ['name' => EmbedCommit::class, 'count' => 1, 'last_failed_at' => '2026-10-19T16:30:00+00:00'],
    ]);
});
