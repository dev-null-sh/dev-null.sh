<?php

declare(strict_types=1);

use App\Jobs\SyncGitHubProfileJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('refreshes the profile of the user', function (): void {
    $user = User::factory()->create(['bio' => 'Old bio']);

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->username, 'bio' => 'New bio']),
    ]);

    dispatch(new SyncGitHubProfileJob($user));

    expect($user->refresh()->bio)->toBe('New bio');
});

it('is queued once per user at a time', function (): void {
    Queue::fake();

    [$user, $other] = User::factory()->count(2)->create();

    dispatch(new SyncGitHubProfileJob($user));
    dispatch(new SyncGitHubProfileJob($user));
    dispatch(new SyncGitHubProfileJob($other));

    Queue::assertPushedTimes(SyncGitHubProfileJob::class, 2);
    Queue::assertPushed(SyncGitHubProfileJob::class, fn (SyncGitHubProfileJob $job): bool => $job->user->is($user));
    Queue::assertPushed(SyncGitHubProfileJob::class, fn (SyncGitHubProfileJob $job): bool => $job->user->is($other));
});
