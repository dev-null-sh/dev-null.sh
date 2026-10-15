<?php

declare(strict_types=1);

use App\Actions\CreateGitHubDiscovery;
use App\Jobs\DiscoverGitHubPeople;
use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

it('starts one queued discovery without making github requests in the web action', function (): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);

    $action = resolve(CreateGitHubDiscovery::class);
    $action->handle($user);
    $action->handle($user);

    $state = resolve(GitHubPeopleQuery::class)->snapshot($user);

    expect($state)->toMatchArray([
        'status' => 'loading', 'github_ids' => [], 'username' => null, 'page' => 0,
        'scanned' => 0, 'fetched_at' => null, 'retry_at' => null,
        'pages_requested' => 0, 'restart_count' => 0, 'truncated' => false,
    ]);

    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
    Queue::assertPushed(DiscoverGitHubPeople::class, fn (DiscoverGitHubPeople $job): bool => $job->user->is($user) && $job->id === $state['id'] && $job->page === 1);
    Http::assertNothingSent();
});

it('reuses a complete snapshot until its cache expires', function (): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    $action = resolve(CreateGitHubDiscovery::class);
    $action->handle($user);

    $query = resolve(GitHubPeopleQuery::class);
    $state = $query->snapshot($user);
    $state['status'] = 'ready';
    $state['github_ids'] = ['123'];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());

    $action->handle($user);

    expect($query->snapshot($user))->toBe($state);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);

    $this->travel(61)->minutes();
    $action->handle($user);

    expect($query->snapshot($user)['id'])->not->toBe($state['id']);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);
});

it('restarts failed discoveries cleanly only after their retry time', function (string $status): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    $action = resolve(CreateGitHubDiscovery::class);
    $action->handle($user);

    $query = resolve(GitHubPeopleQuery::class);
    $state = $query->snapshot($user);
    $state['status'] = $status;
    $state['github_ids'] = ['123'];
    $state['page'] = 2;
    $state['retry_at'] = now()->addMinute()->toIso8601String();
    Cache::put('github-people:'.$user->id, $state, now()->addHour());

    $action->handle($user);

    expect($query->snapshot($user))->toBe($state);
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);

    $this->travel(61)->seconds();
    $action->handle($user);

    expect($query->snapshot($user))->toMatchArray(['status' => 'loading', 'github_ids' => [], 'page' => 0, 'retry_at' => null]);
    expect($query->snapshot($user)['id'])->not->toBe($state['id']);
    Queue::assertPushed(DiscoverGitHubPeople::class, 2);
})->with(['unavailable', 'rate_limited', 'renamed']);

it('does not start competing scans while another request owns the start lock', function (): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);
    $lock = Cache::lock('github-people-start:'.$user->id, 10);
    $lock->get();

    resolve(CreateGitHubDiscovery::class)->handle($user);

    expect(resolve(GitHubPeopleQuery::class)->snapshot($user))->toBeNull();
    Queue::assertNotPushed(DiscoverGitHubPeople::class);
    $lock->release();
});

it('makes a discovery retryable when the first job cannot be enqueued', function (): void {
    $user = User::factory()->create();
    $this->mock(Dispatcher::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dispatch')->once()->with(Mockery::type(DiscoverGitHubPeople::class))
            ->andThrow(new RuntimeException('Queue unavailable'));
    });

    expect(fn () => resolve(CreateGitHubDiscovery::class)->handle($user))->toThrow(RuntimeException::class, 'Queue unavailable');

    expect(resolve(GitHubPeopleQuery::class)->snapshot($user))->toMatchArray([
        'status' => 'unavailable', 'page' => 0, 'github_ids' => [],
        'retry_at' => now()->addMinute()->toIso8601String(),
    ]);
    Http::assertNothingSent();
});

it('preserves earlier results when a replacement discovery cannot be enqueued', function (): void {
    $user = User::factory()->create();
    $state = [
        'id' => 'previous-run', 'status' => 'unavailable', 'github_ids' => ['123'], 'username' => 'octocat',
        'page' => 1, 'scanned' => 1, 'fetched_at' => null, 'retry_at' => now()->subMinute()->toIso8601String(),
    ];
    Cache::put('github-people:'.$user->id, $state, now()->addHour());
    $this->mock(Dispatcher::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
    });

    expect(fn () => resolve(CreateGitHubDiscovery::class)->handle($user))->toThrow(RuntimeException::class);

    expect(resolve(GitHubPeopleQuery::class)->snapshot($user))->toBe([
        ...$state, 'retry_at' => now()->addMinute()->toIso8601String(),
    ]);
});
