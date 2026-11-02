<?php

declare(strict_types=1);

use App\Actions\DeleteFailedJobs;
use Illuminate\Support\Facades\DB;

it('forgets every failed job', function (): void {
    failedJob();
    failedJob(queue: 'unfurls');

    resolve(DeleteFailedJobs::class)->handle();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});
