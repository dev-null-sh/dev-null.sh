<?php

declare(strict_types=1);

use App\Jobs\ImportPendingGitHubFollowsJob;
use Illuminate\Support\Facades\Queue;

it('remembers the pending github follows of every user in the background', function (): void {
    Queue::fake();

    $this->artisan('follows:pending')
        ->expectsOutputToContain('Queued.')
        ->assertSuccessful();

    Queue::assertPushed(ImportPendingGitHubFollowsJob::class, fn (ImportPendingGitHubFollowsJob $job): bool => $job->after === null);
});
