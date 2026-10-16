<?php

declare(strict_types=1);

use App\Actions\CreateFailedJobRetry;
use App\Jobs\UnfurlLink;
use Illuminate\Support\Facades\DB;

it('pushes the failed job back onto its queue with its attempts reset', function (): void {
    $uuid = failedJob(queue: 'previews', payload: ['uuid' => 'job-uuid', 'displayName' => UnfurlLink::class, 'attempts' => 3]);

    resolve(CreateFailedJobRetry::class)->handle($uuid);

    $job = DB::table('jobs')->sole();

    expect($job->queue)->toBe('previews')
        ->and(json_decode((string) $job->payload, true))->toBe(['uuid' => 'job-uuid', 'displayName' => UnfurlLink::class, 'attempts' => 0])
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('does nothing when the failed job is already gone', function (): void {
    resolve(CreateFailedJobRetry::class)->handle('8d1c5a0e-55f4-4b8c-9d7c-0d7f0b5b5a11');

    expect(DB::table('jobs')->count())->toBe(0);
});
