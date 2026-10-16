<?php

declare(strict_types=1);

use App\Actions\CreateGitHubDiscovery;
use App\Jobs\DiscoverGitHubPeople;
use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use App\Services\GitHubFollowing;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Queue\MaxAttemptsExceededException;
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
    Http::assertSentCount(1);
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

it('ignores a redelivered completed page while its continuation is queued', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $id = $query->snapshot($user)['id'];
    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following?per_page=100&page=1' => Http::response([['id' => 123]], 200, ['Link' => '<next>; rel="next"']),
        'api.github.com/users/octocat/following?per_page=100&page=2' => Http::response([['id' => 456]]),
    ]);
    $first = new DiscoverGitHubPeople($user, $id);
    $first->handle(resolve(GitHubFollowing::class), $query);

    $completedPage = $query->snapshot($user);

    $first->failed(new MaxAttemptsExceededException);

    expect($query->snapshot($user))->toBe($completedPage);
    Queue::assertPushed(DiscoverGitHubPeople::class, fn (DiscoverGitHubPeople $job): bool => $job->page === 2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);

    new DiscoverGitHubPeople($user, $id, 2)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'page' => 2, 'github_ids' => ['123', '456']]);
    Http::assertSentCount(4);
});

it('resumes a completed page when its worker failed before dispatching the continuation', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'page' => 1, 'github_ids' => ['123'], 'scanned' => 1];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    expect(Cache::lock("github-people-scan:{$user->id}:{$state['id']}", 90)->get())->toBeTrue();
    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following?per_page=100&page=2' => Http::response([['id' => 456]]),
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->failed(new MaxAttemptsExceededException);

    expect($query->snapshot($user))->toBe($state);
    Queue::assertPushed(DiscoverGitHubPeople::class, fn (DiscoverGitHubPeople $job): bool => $job->page === 2 && $job->delay === 90);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);

    $this->travel(91)->seconds();

    new DiscoverGitHubPeople($user, $state['id'], 2)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'page' => 2, 'github_ids' => ['123', '456']]);
    Http::assertSentCount(3);
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

it('restarts from page one when the cached owner username changes', function (int $status, array $body, array $headers): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'page' => 1, 'pages_requested' => 1, 'username' => 'old-name', 'github_ids' => ['123'], 'scanned' => 1];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());

    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'new-name']),
        'api.github.com/users/old-name/following?per_page=100&page=2' => Http::response($body, $status, $headers),
        'api.github.com/users/new-name/following?per_page=100&page=1' => Http::response([['id' => 456]]),
    ]);

    new DiscoverGitHubPeople($user, $state['id'], 2)->handle(resolve(GitHubFollowing::class), $query);

    $restarted = $query->snapshot($user);

    expect($restarted)->toMatchArray([
        'status' => 'loading', 'username' => 'new-name', 'github_ids' => [], 'page' => 0,
        'scanned' => 0, 'pages_requested' => 2, 'restart_count' => 1, 'fetched_at' => null,
    ]);
    expect($restarted['id'])->not->toBe($state['id']);
    expect($query->get($user)['users'])->toBeEmpty();
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, fn (DiscoverGitHubPeople $job): bool => $job->id === $restarted['id'] && $job->page === 1);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);

    $old = new DiscoverGitHubPeople($user, $state['id'], 2);
    $old->handle(resolve(GitHubFollowing::class), $query);
    $old->failed(new RuntimeException('Old run failed'));

    expect($query->snapshot($user))->toBe($restarted);
    Http::assertSentCount(2);

    new DiscoverGitHubPeople($user, $restarted['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'github_ids' => ['456'], 'pages_requested' => 3]);
    Http::assertSentCount(4);
})->with([
    'old username not found' => [404, ['message' => 'Not Found'], []],
    'old username redirects' => [301, [], ['Location' => 'https://elsewhere.test/following']],
    'old username was reclaimed' => [200, [['id' => 777]], []],
]);

it('caps discovery at five pages and verifies the owner before publishing truncated matches', function (): void {
    $user = User::factory()->create(['github_id' => '9000']);
    $matched = User::factory()->create(['github_id' => '500']);
    User::factory()->create(['github_id' => '501']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $id = $query->snapshot($user)['id'];
    Http::fake([
        'api.github.com/user/9000' => Http::response(['id' => 9000, 'login' => 'octocat']),
        'api.github.com/users/octocat/following*' => fn ($request) => Http::response(
            array_map(fn (int $id): array => ['id' => $id], range(((int) $request['page'] - 1) * 100 + 1, (int) $request['page'] * 100)),
            200, ['Link' => '<next>; rel="next"'],
        ),
    ]);

    foreach (range(1, 5) as $page) {
        new DiscoverGitHubPeople($user, $id, $page)->handle(resolve(GitHubFollowing::class), $query);
    }

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'scanned' => 500, 'page' => 5, 'pages_requested' => 5, 'truncated' => true]);
    expect($query->get($user)['users']->pluck('id')->all())->toBe([$matched->id]);
    Queue::assertPushed(DiscoverGitHubPeople::class, 5);
    Queue::assertNotPushed(DiscoverGitHubPeople::class, fn (DiscoverGitHubPeople $job): bool => $job->page === 6);
    Http::assertSentCount(7);
});

it('does not truncate a full final page when github has no next page', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'page' => 4, 'pages_requested' => 4, 'username' => 'octocat'];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following*' => Http::response(array_map(fn (int $id): array => ['id' => $id], range(1, 100)), 200, ['Link' => '<prev>; rel="prev"']),
    ]);

    new DiscoverGitHubPeople($user, $state['id'], 5)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'pages_requested' => 5, 'truncated' => false]);
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('keeps the original page budget after a rename restart', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'pages_requested' => 4, 'restart_count' => 1, 'username' => 'new-name'];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'new-name']),
        'api.github.com/users/new-name/following*' => Http::response(array_map(fn (int $id): array => ['id' => $id], range(1, 100)), 200, ['Link' => '<next>; rel="next"']),
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'scanned' => 100, 'pages_requested' => 5, 'truncated' => true]);
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('stops retryably after a repeated rename or an exhausted scan budget', function (int $requested, int $restarts): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'pages_requested' => $requested, 'restart_count' => $restarts, 'username' => 'old-name', 'github_ids' => ['123']];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'new-name']),
        'api.github.com/users/old-name/following*' => Http::response([['id' => 777]]),
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray([
        'status' => 'renamed', 'github_ids' => [], 'scanned' => 0, 'page' => 0, 'fetched_at' => null,
        'retry_at' => now()->addMinute()->toIso8601String(), 'pages_requested' => $requested + 1,
    ]);
    expect($query->get($user)['users'])->toBeEmpty();
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
})->with(['second rename' => [2, 1], 'budget exhausted' => [4, 0]]);

it('treats an unchanged or inaccessible owner after a missing page as unavailable', function (int $status, array $owner): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'username' => 'old-name'];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    Http::fake([
        'api.github.com/user/42' => Http::response($owner, $status),
        'api.github.com/users/old-name/following*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['id' => $state['id'], 'status' => 'unavailable', 'restart_count' => 0]);
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
})->with([
    'unchanged' => [200, ['id' => 42, 'login' => 'old-name']],
    'deleted' => [404, ['message' => 'Not Found']],
    'different account' => [200, ['id' => 99, 'login' => 'new-name']],
]);

it('makes the replacement run retryable when its first job cannot be enqueued', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'username' => 'old-name'];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    $this->mock(Dispatcher::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
    });
    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'new-name']),
        'api.github.com/users/old-name/following*' => Http::response([], 404),
    ]);

    expect(fn () => new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query))->toThrow(RuntimeException::class);

    $replacement = $query->snapshot($user);

    expect($replacement)->toMatchArray(['status' => 'unavailable', 'github_ids' => [], 'restart_count' => 1, 'pages_requested' => 1]);
    expect($replacement['id'])->not->toBe($state['id']);

    new DiscoverGitHubPeople($user, $state['id'])->failed(new RuntimeException('Old worker failed'));

    expect($query->snapshot($user))->toBe($replacement);
    Http::assertSentCount(2);
});

it('does not request a sixth page from a snapshot created before the cap', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'page' => 5, 'username' => 'octocat', 'github_ids' => ['123'], 'scanned' => 1];
    unset($state['pages_requested']);
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    Http::fake(['api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'octocat'])]);

    new DiscoverGitHubPeople($user, $state['id'], 6)->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'github_ids' => ['123'], 'page' => 5, 'truncated' => true]);
    Http::assertSentCount(1);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('keeps matches hidden if final owner verification is rate limited', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'username' => 'octocat'];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    Http::fake([
        'api.github.com/users/octocat/following*' => Http::response([['id' => 123]]),
        'api.github.com/user/42' => Http::response([], 429, ['Retry-After' => '90']),
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toMatchArray([
        'status' => 'rate_limited', 'page' => 0, 'github_ids' => [], 'pages_requested' => 1,
        'fetched_at' => null, 'retry_at' => now()->addSeconds(90)->toIso8601String(),
    ]);
    expect($query->get($user)['users'])->toBeEmpty();
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('does not overwrite a replacement run during the initial owner lookup', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = $query->snapshot($user);
    $new = [...$state, 'id' => 'newer-run'];
    Http::fake(['api.github.com/user/42' => function () use ($user, $new) {
        Cache::put('github-people:'.$user->id, $new, now()->addHour());

        return Http::response(['id' => 42, 'login' => 'octocat']);
    }]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toBe($new);
    Http::assertSentCount(1);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('does not publish matches after final verification replaces the run', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $state = [...$query->snapshot($user), 'username' => 'octocat'];
    $new = [...$state, 'id' => 'newer-run'];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    Http::fake([
        'api.github.com/users/octocat/following*' => Http::response([['id' => 123]]),
        'api.github.com/user/42' => function () use ($user, $new) {
            Cache::put('github-people:'.$user->id, $new, now()->addHour());

            return Http::response(['id' => 42, 'login' => 'octocat']);
        },
    ]);

    new DiscoverGitHubPeople($user, $state['id'])->handle(resolve(GitHubFollowing::class), $query);

    expect($query->snapshot($user))->toBe($new);
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});

it('recovers a persisted rename restart whose first job was never enqueued', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $old = $query->snapshot($user);
    $replacement = [
        ...$old, 'id' => 'replacement-run', 'previous_id' => $old['id'],
        'username' => 'new-name', 'restart_count' => 1, 'pages_requested' => 2,
    ];
    Cache::put('github-people:'.$user->id, $replacement, now()->addHour());
    Http::fake([
        'api.github.com/users/new-name/following*' => Http::response([['id' => 456]]),
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'new-name']),
    ]);

    $oldJob = new DiscoverGitHubPeople($user, $old['id']);
    $oldJob->failed(new MaxAttemptsExceededException);
    $oldJob->failed(new MaxAttemptsExceededException);

    expect($query->snapshot($user))->toBe($replacement);
    Queue::assertPushed(DiscoverGitHubPeople::class, fn (DiscoverGitHubPeople $job): bool => $job->id === 'replacement-run' && $job->page === 1 && $job->delay === 90);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);

    $this->travel(91)->seconds();

    new DiscoverGitHubPeople($user, $replacement['id'])->handle(resolve(GitHubFollowing::class), $query);
    $oldJob->failed(new MaxAttemptsExceededException);

    expect($query->snapshot($user))->toMatchArray(['status' => 'ready', 'github_ids' => ['456'], 'pages_requested' => 3]);
    Http::assertSentCount(2);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);
});

it('does not recover a replacement after it has advanced or when it has a different parent', function (array $changes): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    resolve(CreateGitHubDiscovery::class)->handle($user);
    $query = resolve(GitHubPeopleQuery::class);
    $old = $query->snapshot($user);
    $replacement = [...$old, 'id' => 'replacement-run', 'previous_id' => $old['id'], ...$changes];
    Cache::put('github-people:'.$user->id, $replacement, now()->addHour());

    new DiscoverGitHubPeople($user, $old['id'])->failed(new MaxAttemptsExceededException);

    expect($query->snapshot($user))->toBe($replacement);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
    Http::assertNothingSent();
})->with([
    'advanced' => [['page' => 1]],
    'finished' => [['status' => 'ready']],
    'unrelated' => [['previous_id' => 'other-run']],
]);
