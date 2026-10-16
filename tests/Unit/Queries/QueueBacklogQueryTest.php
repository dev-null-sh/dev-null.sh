<?php

declare(strict_types=1);

use App\Queries\QueueBacklogQuery;

it('always lists the default queue', function (): void {
    expect(resolve(QueueBacklogQuery::class)->get())
        ->toBe([['queue' => 'default', 'size' => 0, 'oldest_job_age' => null]]);
});

it('counts the jobs waiting on each queue and the age of the oldest one', function (): void {
    queuedJob('default', now()->subSeconds(30));
    queuedJob('previews', now()->subMinutes(10));
    queuedJob('previews', now()->subMinute());

    expect(resolve(QueueBacklogQuery::class)->get())->toBe([
        ['queue' => 'default', 'size' => 1, 'oldest_job_age' => 30],
        ['queue' => 'previews', 'size' => 2, 'oldest_job_age' => 600],
    ]);
});
