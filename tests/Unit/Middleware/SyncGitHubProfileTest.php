<?php

declare(strict_types=1);

use App\Http\Middleware\SyncGitHubProfile;
use App\Jobs\SyncGitHubProfileJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;

function syncGitHubProfileFor(?User $user): Response
{
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => $user);

    return new SyncGitHubProfile()->handle($request, fn (Request $request): Response => response('OK'));
}

it('refreshes a stale profile in the background', function (?int $hoursAgo): void {
    Queue::fake();

    $user = User::factory()->create(['github_synced_at' => $hoursAgo === null ? null : now()->subHours($hoursAgo)]);

    $response = syncGitHubProfileFor($user);

    expect($response->getContent())->toBe('OK');

    Queue::assertPushed(SyncGitHubProfileJob::class, fn (SyncGitHubProfileJob $job): bool => $job->user->is($user));
})->with([
    'never synced' => null,
    'synced a day ago' => 24,
    'synced a week ago' => 168,
]);

it('leaves a fresh profile alone', function (): void {
    Queue::fake();

    $user = User::factory()->create(['github_synced_at' => now()->subDay()->addMinute()]);

    syncGitHubProfileFor($user);

    Queue::assertNothingPushed();
});

it('leaves the local developer alone', function (): void {
    Queue::fake();

    $user = User::factory()->create(['github_id' => 'local', 'github_synced_at' => null]);

    syncGitHubProfileFor($user);

    Queue::assertNothingPushed();
});

it('does nothing for guests', function (): void {
    Queue::fake();

    expect(syncGitHubProfileFor(null)->getContent())->toBe('OK');

    Queue::assertNothingPushed();
});

it('refreshes once while the job is waiting in the queue', function (): void {
    Queue::fake();

    $user = User::factory()->create(['github_synced_at' => null]);

    $this->actingAs($user)->get(route('home'));
    $this->actingAs($user)->get(route('home'));

    Queue::assertPushedTimes(SyncGitHubProfileJob::class, 1);
});
