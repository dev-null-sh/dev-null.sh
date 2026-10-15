<?php

declare(strict_types=1);

use App\Actions\CreateGitHubDiscovery;
use App\Jobs\DiscoverGitHubPeople;
use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use App\Services\GitHubFollowing;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

it('scans one page per job and completes only after the final page', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $id = $query->snapshot($user)['id'];

    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following?per_page=100&page=1' => Http::response(
            array_map(fn (int $id): array => ['id' => $id], range(1, 100)), 200,
            ['Link' => '<https://untrusted.example/next>; rel="next"'],
        ),
        'api.github.com/users/octocat/following?per_page=100&page=2' => Http::response([['id' => 101], ['id' => 100]]),
    ]);

    $first = new DiscoverGitHubPeople($user, $id);
    $first->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'loading', 'page' => 1, 'scanned' => 100, 'fetched_at' => null]);
    Queue::assertPushed(DiscoverGitHubPeople::class, fn (DiscoverGitHubPeople $job): bool => $job->id === $id && $job->page === 2);
    Http::assertSentCount(2);

    new DiscoverGitHubPeople($user, $id, 2)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'page' => 2, 'scanned' => 101, 'fetched_at' => now()->toIso8601String()]);
    expect($query->snapshot($user)['github_ids'])->toHaveCount(101);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);
    Http::assertSentCount(4);

    $first->handle(resolve(GitHubFollowing::class), $query);

    Http::assertSentCount(4);
});

it('ignores stale runs and pages that are not the next page', function (): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = $query->snapshot($user);

    new DiscoverGitHubPeople($user, 'previous-run')->handle(resolve(GitHubFollowing::class), $query);
    new DiscoverGitHubPeople($user, $state['id'], 2)->handle(resolve(GitHubFollowing::class), $query);
    new DiscoverGitHubPeople($user, 'previous-run')->failed(new RuntimeException('Old failure'));

    expect($query->snapshot($user))->toBe($state);
    Http::assertNothingSent();
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('does not let an in flight response overwrite a newer discovery', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = $query->snapshot($user);
    $new = [...$state, 'id' => 'newer-run'];

    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following*' => function () use ($user, $new) {
            Cache::put('github-people:'.$user->id, $new, now()->addHour());

            return Http::response([['id' => 123]]);
        },
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toBe($new);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('preserves partial results and records github rate limit retry times', function (int $status, array $headers, int $retrySeconds): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = $query->snapshot($user);
    $state['username'] = 'octocat';
    $state['github_ids'] = ['123'];
    $state['scanned'] = 1;
    $state['page'] = 1;
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    $headers = array_map(fn (string $value): string => $value === 'reset' ? (string) now()->addSeconds($retrySeconds)->getTimestamp() : $value, $headers);

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['id' => $user->github_id, 'login' => 'octocat']),
        'api.github.com/users/octocat/following*' => Http::response(['message' => 'Limited'], $status, $headers),
    ]);

    new DiscoverGitHubPeople($user, $state['id'], 2)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray([
        'status' => 'rate_limited', 'github_ids' => ['123'], 'scanned' => 1,
        'retry_at' => now()->addSeconds($retrySeconds)->toIso8601String(),
    ]);
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
})->with([
    'primary limit' => [403, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => 'reset'], 120],
    'secondary limit' => [429, ['Retry-After' => '90'], 90],
]);

it('records unavailable instead of an empty success when github cannot be reached', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $id = $query->snapshot($user)['id'];

    Http::fake(['api.github.com/user/42' => Http::failedConnection()]);

    new DiscoverGitHubPeople($user, $id)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray([
        'status' => 'unavailable', 'page' => 0, 'fetched_at' => null,
        'retry_at' => now()->addMinute()->toIso8601String(),
    ]);
    Http::assertSentCount(3);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('makes an unexpected current job failure retryable', function (): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $id = $query->snapshot($user)['id'];

    new DiscoverGitHubPeople($user, $id)->failed(new RuntimeException('Worker failed'));

    expect($query->snapshot($user)['status'])->toBe('unavailable');
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('makes partial discovery retryable when the next page cannot be enqueued', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $id = $query->snapshot($user)['id'];
    $this->mock(Dispatcher::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dispatch')->once()->with(Mockery::on(fn (DiscoverGitHubPeople $job): bool => $job->page === 2))
            ->andThrow(new RuntimeException('Queue unavailable'));
    });

    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following*' => Http::response([['id' => 123]], 200, ['Link' => '<next>; rel="next"']),
    ]);

    expect(fn () => new DiscoverGitHubPeople($user, $id)->handle(resolve(GitHubFollowing::class), $query))
        ->toThrow(RuntimeException::class, 'Queue unavailable');

    expect($query->snapshot($user))->toMatchArray([
        'status' => 'unavailable', 'page' => 1, 'github_ids' => ['123'], 'scanned' => 1,
        'retry_at' => now()->addMinute()->toIso8601String(),
    ]);
    Http::assertSentCount(2);
});

it('does not let failure callbacks replace later completed pages', function (): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'page' => 2, 'github_ids' => ['123'], 'scanned' => 1];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());

    new DiscoverGitHubPeople($user, $state['id'])->failed(new RuntimeException('Old failure'));

    expect($query->snapshot($user))->toBe($state);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('does not let an in flight failure replace a newer discovery', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = $query->snapshot($user);
    $new = [...$state, 'id' => 'newer-run'];

    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following*' => function () use ($user, $new) {
            Cache::put('github-people:'.$user->id, $new, now()->addHour());

            return Http::response(['message' => 'Forbidden'], 403);
        },
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toBe($new);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('resolves a renamed owner again between queued pages', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'page' => 1, 'username' => 'old-name', 'github_ids' => ['123'], 'scanned' => 1];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());

    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'new-name']),
        'api.github.com/users/new-name/following?per_page=100&page=2' => Http::response([['id' => 456]]),
    ]);

    new DiscoverGitHubPeople($user, $state['id'], 2)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'username' => 'new-name', 'github_ids' => ['123', '456']]);
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});
