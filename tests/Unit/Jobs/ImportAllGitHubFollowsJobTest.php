<?php

declare(strict_types=1);

use App\Actions\CreateUserGitHubFollows;
use App\Contracts\Services\GitHubProvider;
use App\Jobs\ImportAllGitHubFollowsJob;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('imports the github follows of the users not imported yet, without notifying anyone, and says when it is done', function (): void {
    Queue::fake();
    Log::spy();

    $alice = User::factory()->create(['handle' => 'alice']);
    $bob = User::factory()->create(['handle' => 'bob', 'github_follows_imported_at' => now()->subDay()]);
    $imported = User::factory()->create(['github_follows_imported_at' => now()->subDay(), 'github_organization_follows_imported_at' => now()->subDay()]);
    $organization = Organization::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('followingIds')->with('alice', 10)->andReturn(['users' => [$bob->github_id], 'organizations' => []]);
    $github->shouldReceive('followerIds')->with('alice', 10)->andReturn([]);
    $github->shouldReceive('followingIds')->with('bob', 10)->andReturn(['users' => [], 'organizations' => [$organization->profile->github_id]]);

    new ImportAllGitHubFollowsJob()->handle($github, resolve(CreateUserGitHubFollows::class));

    expect($alice->isFollowing($bob))->toBeTrue()
        ->and($bob->followedOrganizations()->pluck('organizations.id')->all())->toBe([$organization->id])
        ->and(Notification::query()->count())->toBe(0);

    $github->shouldNotHaveReceived('followingIds', [$imported->handle, 10]);
    Queue::assertNothingPushed();
    Log::shouldHaveReceived('info')->with('Went through every user whose GitHub follows were not imported yet.')->once();
});

it('queues the next batch after a full one, from the last user it read', function (): void {
    Queue::fake();

    $users = User::factory()->count(ImportAllGitHubFollowsJob::BatchSize + 1)->create()->sortBy('id')->values();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('followingIds')->andReturn(['users' => [], 'organizations' => []]);
    $github->shouldReceive('followerIds')->andReturn([]);

    new ImportAllGitHubFollowsJob()->handle($github, resolve(CreateUserGitHubFollows::class));

    expect($users->last()->refresh()->github_follows_imported_at)->toBeNull();
    Queue::assertPushed(ImportAllGitHubFollowsJob::class, fn (ImportAllGitHubFollowsJob $job): bool => $job->after === $users[ImportAllGitHubFollowsJob::BatchSize - 1]->id);
});

it('waits for the rate limit to reset, at most 15 minutes at a time, keeping requests for the rest of the app', function (int $remaining, int $resetsInMinutes, int $delayMinutes): void {
    Queue::fake();
    $this->freezeSecond();

    User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturn(['remaining' => $remaining, 'limit' => 5000, 'resets_at' => CarbonImmutable::now()->addMinutes($resetsInMinutes)]);
    $github->shouldReceive('followingIds')->never();

    new ImportAllGitHubFollowsJob('previous')->handle($github, resolve(CreateUserGitHubFollows::class));

    Queue::assertPushed(ImportAllGitHubFollowsJob::class, fn (ImportAllGitHubFollowsJob $job): bool => $job->after === 'previous'
        && $job->delay instanceof DateTimeInterface
        && CarbonImmutable::instance($job->delay)->equalTo(now()->addMinutes($delayMinutes)));
})->with([
    'reset soon' => [1999, 5, 6],
    'reset in an hour' => [1000, 60, 15],
]);

it('waits when github rate limits it, as long as github asks or 15 minutes, and imports that user then', function (int $status, array $headers, int $delaySeconds): void {
    Queue::fake();
    $this->freezeSecond();

    [$imported, $limited] = User::factory()->count(2)->create()->sortBy('id')->values();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('followingIds')->with($imported->handle, 10)->andReturn(['users' => [], 'organizations' => []]);
    $github->shouldReceive('followerIds')->andReturn([]);
    $github->shouldReceive('followingIds')->with($limited->handle, 10)->andThrow(new RequestException(new Response(new Psr7Response($status, $headers, '{"message":"You have exceeded a secondary rate limit."}'))));

    new ImportAllGitHubFollowsJob()->handle($github, resolve(CreateUserGitHubFollows::class));

    expect($limited->refresh()->github_follows_imported_at)->toBeNull();
    Queue::assertPushed(ImportAllGitHubFollowsJob::class, fn (ImportAllGitHubFollowsJob $job): bool => $job->after === $imported->id
        && CarbonImmutable::instance($job->delay)->equalTo(now()->addSeconds($delaySeconds)));
})->with([
    '403' => [403, [], 900],
    '429' => [429, [], 900],
    'retry after' => [403, ['Retry-After' => '60'], 60],
]);

it('leaves the rest of the batch to the next job once it has spent its budget', function (): void {
    Queue::fake();
    $this->freezeSecond();

    [$slow, $next] = User::factory()->count(2)->create()->sortBy('id')->values();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('followingIds')->with($slow->handle, 10)->andReturnUsing(function (): array {
        $this->travel(ImportAllGitHubFollowsJob::Budget)->seconds();

        return ['users' => [], 'organizations' => []];
    });
    $github->shouldReceive('followerIds')->andReturn([]);
    $github->shouldReceive('followingIds')->with($next->handle, 10)->never();

    new ImportAllGitHubFollowsJob()->handle($github, resolve(CreateUserGitHubFollows::class));

    expect($slow->refresh()->github_follows_imported_at)->not->toBeNull();
    Queue::assertPushed(ImportAllGitHubFollowsJob::class, fn (ImportAllGitHubFollowsJob $job): bool => $job->after === $slow->id
        && CarbonImmutable::instance($job->delay)->equalTo(now()));
});

it('skips the users github fails on, reporting all but the accounts renamed since their last sign-in', function (): void {
    Queue::fake();
    Exceptions::fake();

    [$renamed, $forbidden, $failing, $unreachable, $user] = User::factory()->count(5)->create()->sortBy('id')->values();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('followingIds')->with($renamed->handle, 10)->andThrow(new RequestException(new Response(new Psr7Response(404))));
    $github->shouldReceive('followingIds')->with($forbidden->handle, 10)->andThrow(new RequestException(new Response(new Psr7Response(403, [], '{"message":"Forbidden"}'))));
    $github->shouldReceive('followingIds')->with($failing->handle, 10)->andThrow(new RequestException(new Response(new Psr7Response(500))));
    $github->shouldReceive('followingIds')->with($unreachable->handle, 10)->andThrow(new ConnectionException());
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [], 'organizations' => []]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    new ImportAllGitHubFollowsJob()->handle($github, resolve(CreateUserGitHubFollows::class));

    expect($renamed->refresh()->github_follows_imported_at)->toBeNull()
        ->and($user->refresh()->github_follows_imported_at)->not->toBeNull();

    Exceptions::assertReportedCount(3);
});
