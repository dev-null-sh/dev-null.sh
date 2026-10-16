<?php

declare(strict_types=1);

use App\Jobs\UnfurlLink;
use App\Queries\FailedJobsQuery;

it('has no failed jobs', function (): void {
    expect(resolve(FailedJobsQuery::class)->get())
        ->toBe(['total' => 0, 'recent' => []]);
});

it('lists the most recent failed jobs with the first line of their exception', function (): void {
    $older = failedJob(failedAt: now()->subHour());
    $newer = failedJob(queue: 'previews', exception: "RuntimeException: Connection refused in /app/Jobs/UnfurlLink.php:42\nStack trace:\n#0 {main}");

    $failedJobs = resolve(FailedJobsQuery::class)->get(limit: 1);

    expect($failedJobs['total'])->toBe(2)
        ->and($failedJobs['recent'])->toBe([[
            'uuid' => $newer,
            'name' => UnfurlLink::class,
            'queue' => 'previews',
            'exception' => 'RuntimeException: Connection refused in /app/Jobs/UnfurlLink.php:42',
            'failed_at' => now()->toIso8601String(),
        ]])
        ->and($older)->not->toBe($newer);
});

it('falls back to the job class when the payload has no display name', function (): void {
    failedJob(payload: ['uuid' => 'x', 'job' => 'Illuminate\Queue\CallQueuedHandler@call']);
    failedJob(payload: []);

    expect(array_column(resolve(FailedJobsQuery::class)->get()['recent'], 'name'))
        ->toBe(['Unknown job', 'Illuminate\Queue\CallQueuedHandler@call']);
});
