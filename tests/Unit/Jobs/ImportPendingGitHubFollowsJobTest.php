<?php

declare(strict_types=1);

use App\Jobs\ImportPendingGitHubFollowsJob;
use App\Models\PendingFollow;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

function importPendingGitHubFollows(?string $after = null): void
{
    app()->call([new ImportPendingGitHubFollowsJob($after), 'handle']);
}

function fakeGitHubRateLimit(int $remaining, DateTimeInterface $resetsAt): void
{
    config(['services.github.client_id' => 'client-id', 'services.github.client_secret' => 'client-secret']);

    Http::fake(['api.github.com/rate_limit' => Http::response([
        'resources' => ['core' => ['limit' => 5000, 'remaining' => $remaining, 'reset' => $resetsAt->getTimestamp()]],
    ])]);
}

it('remembers whom a batch of imported users follow on github, then queues the next batch', function (): void {
    $users = User::factory()->count(ImportPendingGitHubFollowsJob::BatchSize + 1)->create(['github_follows_imported_at' => now()]);
    User::factory()->create(['github_follows_imported_at' => null]);
    [$batch, $next] = $users->sortBy('id')->values()->partition(fn (User $user, int $index): bool => $index < ImportPendingGitHubFollowsJob::BatchSize);

    Http::fake(['api.github.com/users/*/following*' => Http::response([['id' => 111, 'login' => 'octocat']])]);

    importPendingGitHubFollows();

    expect(PendingFollow::query()->where('github_id', '111')->pluck('user_id')->sort()->values()->all())->toBe($batch->pluck('id')->all());

    Http::assertSentCount(ImportPendingGitHubFollowsJob::BatchSize);
    Queue::assertPushed(ImportPendingGitHubFollowsJob::class, fn (ImportPendingGitHubFollowsJob $job): bool => $job->after === $batch->last()->id
        && now()->equalTo($job->delay));

    expect($next->first()->pendingFollows()->exists())->toBeFalse();
});

it('reads the users after the last batch, and stops after the last of them', function (): void {
    $first = User::factory()->create(['github_follows_imported_at' => now()]);
    $second = User::factory()->create(['github_follows_imported_at' => now()]);
    [$first, $second] = collect([$first, $second])->sortBy('id')->values()->all();

    Log::spy();
    fakeGitHubFollows($second, following: [User::factory()->make(['github_id' => '222'])]);

    importPendingGitHubFollows(after: $first->id);

    expect(PendingFollow::query()->pluck('user_id')->all())->toBe([$second->id]);

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('info')->with('Remembered the pending GitHub follows of every user.');
});

it('skips the users whose github account was renamed or deleted since they signed in', function (): void {
    $user = User::factory()->create(['github_follows_imported_at' => now()]);

    Exceptions::fake();
    Http::fake(["api.github.com/users/{$user->username}/following*" => Http::response(['message' => 'Not Found'], 404)]);

    importPendingGitHubFollows();

    expect(PendingFollow::query()->count())->toBe(0);

    Exceptions::assertNothingReported();
});

it('keeps going when whom a user follows cannot be read', function (string $exception, Closure $response): void {
    $failing = User::factory()->create(['username' => 'failing', 'github_follows_imported_at' => now()]);
    $user = User::factory()->create(['github_follows_imported_at' => now()]);

    Exceptions::fake();
    Http::fake(['api.github.com/users/failing/following*' => $response]);
    fakeGitHubFollows($user, following: [User::factory()->make(['github_id' => '222'])]);

    importPendingGitHubFollows();

    expect(PendingFollow::query()->pluck('user_id')->all())->toBe([$user->id]);

    Exceptions::assertReported($exception);
})->with([
    'server error' => [RequestException::class, fn (): Closure => fn () => Http::response(['message' => 'Server Error'], 500)],
    'connection error' => [ConnectionException::class, fn (): Closure => fn () => Http::failedConnection()],
]);

it('waits for the rate limit to reset when it gets down to what the rest of the app needs', function (int $minutesToReset, int $minutesToWait): void {
    // GitHub gives the reset to the second.
    $this->travelTo(now()->startOfSecond());

    $user = User::factory()->create(['github_follows_imported_at' => now()]);

    fakeGitHubRateLimit(ImportPendingGitHubFollowsJob::Reserve - 1, now()->addMinutes($minutesToReset));

    importPendingGitHubFollows(after: 'resume-here');

    Http::assertSentCount(1);
    Queue::assertPushed(ImportPendingGitHubFollowsJob::class, fn (ImportPendingGitHubFollowsJob $job): bool => $job->after === 'resume-here'
        && now()->addMinutes($minutesToWait)->equalTo($job->delay));

    expect($user->pendingFollows()->exists())->toBeFalse();
})->with([
    'a minute past the reset' => [10, 11],
    'at most 15 minutes, to read it again' => [50, 15],
]);

it('goes on while the rate limit leaves enough to the rest of the app', function (): void {
    $user = User::factory()->create(['github_follows_imported_at' => now()]);

    fakeGitHubRateLimit(ImportPendingGitHubFollowsJob::Reserve, now()->addHour());
    fakeGitHubFollows($user, following: [User::factory()->make(['github_id' => '222'])]);

    importPendingGitHubFollows();

    expect($user->pendingFollows()->pluck('github_id')->all())->toBe(['222']);
});

it('tries again later from the user github rate limited', function (int $status): void {
    $first = User::factory()->create(['github_follows_imported_at' => now()]);
    $second = User::factory()->create(['github_follows_imported_at' => now()]);
    [$first, $second] = collect([$first, $second])->sortBy('id')->values()->all();

    fakeGitHubFollows($first, following: [User::factory()->make(['github_id' => '111'])]);
    Http::fake(["api.github.com/users/{$second->username}/following*" => Http::response(['message' => 'API rate limit exceeded'], $status)]);

    importPendingGitHubFollows();

    expect(PendingFollow::query()->pluck('user_id')->all())->toBe([$first->id]);

    Queue::assertPushed(ImportPendingGitHubFollowsJob::class, fn (ImportPendingGitHubFollowsJob $job): bool => $job->after === $first->id
        && now()->addMinutes(15)->equalTo($job->delay));
})->with([403, 429]);
