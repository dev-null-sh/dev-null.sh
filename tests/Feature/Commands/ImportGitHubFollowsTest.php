<?php

declare(strict_types=1);

use App\Jobs\ImportAllGitHubFollowsJob;
use Illuminate\Support\Facades\Queue;

it('queues the import of every user not imported yet', function (): void {
    Queue::fake();

    $this->artisan('follows:import')
        ->expectsOutputToContain('Queued the import, 25 users at a time. It waits for the GitHub rate limit to reset by itself, keeping 1500 requests for the rest of the app')
        ->assertSuccessful();

    Queue::assertPushed(ImportAllGitHubFollowsJob::class, fn (ImportAllGitHubFollowsJob $job): bool => $job->after === null);
});
