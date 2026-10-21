<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\UserPullRequestsQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('reads seeded data through the normal cache in any environment', function (int $count): void {
    $this->app['env'] = 'production';
    $user = User::factory()->create(['github_id' => 'fixture:developer']);
    $data = ['count' => $count, 'url' => 'https://github.com/search'];
    Cache::put('github-pull-requests:'.$user->github_id, $data, 900);
    $query = resolve(UserPullRequestsQuery::class);

    expect($query->get($user))->toBe($data);
    Http::assertNothingSent();

    $this->travel(16)->minutes();

    expect($query->get($user))->toBeNull();
    Http::assertNothingSent();
})->with([0, 42]);

it('counts public merged pull requests using the current github identity', function (int $count): void {
    $user = User::factory()->create(['github_id' => '123', 'username' => 'old-name']);
    Http::fake([
        'api.github.com/user/123' => Http::response(['login' => 'current-name']),
        'api.github.com/search/issues*' => Http::response(['total_count' => $count, 'incomplete_results' => false]),
    ]);

    $result = resolve(UserPullRequestsQuery::class)->get($user);

    expect($result)->toBe([
        'count' => $count,
        'url' => 'https://github.com/search?type=pullrequests&q='.rawurlencode('is:public is:pr is:merged author:current-name'),
    ]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/search/issues')
        && $request['q'] === 'is:public is:pr is:merged author:current-name'
        && $request['per_page'] === 1);
})->with([0, 42]);

it('reuses the cached count for fifteen minutes', function (): void {
    $user = User::factory()->create(['github_id' => '123']);
    Http::fake([
        'api.github.com/user/123' => Http::response(['login' => 'octocat']),
        'api.github.com/search/issues*' => Http::sequence()
            ->push(['total_count' => 1, 'incomplete_results' => false])
            ->push(['total_count' => 2, 'incomplete_results' => false]),
    ]);
    $query = resolve(UserPullRequestsQuery::class);

    expect($query->get($user)['count'])->toBe(1)
        ->and($query->get($user)['count'])->toBe(1);
    Http::assertSentCount(2);

    $this->travel(16)->minutes();

    expect($query->get($user)['count'])->toBe(2);
    Http::assertSentCount(4);
});

it('does not show zero for failed or incomplete github searches', function (array $body, int $status): void {
    $user = User::factory()->create(['github_id' => '123']);
    Http::fake([
        'api.github.com/user/123' => Http::response(['login' => 'octocat']),
        'api.github.com/search/issues*' => Http::response($body, $status),
    ]);
    $query = resolve(UserPullRequestsQuery::class);

    expect($query->get($user))->toBeNull()
        ->and($query->get($user))->toBeNull();
    Http::assertSentCount(2);
})->with([
    'rate limited' => [[], 429],
    'forbidden' => [[], 403],
    'unavailable' => [[], 503],
    'incomplete' => [['total_count' => 10, 'incomplete_results' => true], 200],
    'missing count' => [['incomplete_results' => false], 200],
    'missing completeness' => [['total_count' => 10], 200],
    'negative count' => [['total_count' => -1, 'incomplete_results' => false], 200],
    'invalid count' => [['total_count' => '10', 'incomplete_results' => false], 200],
]);

it('handles a deleted github account', function (): void {
    $user = User::factory()->create(['github_id' => '123']);
    Http::fake(['api.github.com/user/123' => Http::response(status: 404)]);

    expect(resolve(UserPullRequestsQuery::class)->get($user))->toBeNull();
    Http::assertSentCount(1);
});

it('handles a github connection failure', function (): void {
    $user = User::factory()->create(['github_id' => '123']);
    Http::fake(['api.github.com/*' => Http::failedConnection()]);

    expect(resolve(UserPullRequestsQuery::class)->get($user))->toBeNull();
});

it('does not query github for bots or local accounts', function (string $githubId): void {
    $user = User::factory()->create(['github_id' => $githubId]);

    expect(resolve(UserPullRequestsQuery::class)->get($user))->toBeNull();
    Http::assertNothingSent();
})->with(['local', ...User::BOT_GITHUB_IDS]);

it('does not expose pull requests for bots even when a count is cached', function (string $githubId): void {
    $user = User::factory()->create(['github_id' => $githubId]);
    Cache::put('github-pull-requests:'.$githubId, ['count' => 42, 'url' => 'https://github.com/search'], 900);
    $query = resolve(UserPullRequestsQuery::class);

    expect($query->get($user))->toBeNull();
    Http::assertNothingSent();
})->with(User::BOT_GITHUB_IDS);
