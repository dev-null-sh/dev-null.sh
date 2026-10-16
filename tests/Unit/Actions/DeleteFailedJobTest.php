<?php

declare(strict_types=1);

use App\Actions\DeleteFailedJob;
use Illuminate\Support\Facades\DB;

it('forgets the failed job', function (): void {
    $uuid = failedJob();
    $kept = failedJob();

    resolve(DeleteFailedJob::class)->handle($uuid);

    expect(DB::table('failed_jobs')->pluck('uuid')->all())->toBe([$kept]);
});
