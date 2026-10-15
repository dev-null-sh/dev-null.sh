<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\GitHubFollowing;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('resolves renamed owners by immutable github id and returns only unique account ids', function (): void {
    $user = User::factory()->make(['github_id' => '42', 'username' => 'old-name']);

    Http::fake([
        'api.github.com/user/42' => Http::response(['id' => 42, 'login' => 'current-name']),
        'api.github.com/users/current-name/following*' => Http::response([
            ['id' => 123, 'login' => 'friend', 'email' => 'private@example.test'],
            ['id' => '123', 'login' => 'friend'],
        ]),
    ]);

    $result = resolve(GitHubFollowing::class)->getPage($user, 1);

    expect($result)->toBe(['username' => 'current-name', 'github_ids' => ['123'], 'has_more' => false]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/users/current-name/following?per_page=100&page=1');
});

it('resolves the owner again before requesting later pages on the fixed github endpoint', function (): void {
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);

    Http::fake([
        'api.github.com/users/octocat/following*' => Http::response([['id' => 1]], 200, [
            'Link' => '<https://elsewhere.test/steal>; rel="next"',
        ]),
    ]);

    $result = resolve(GitHubFollowing::class)->getPage($user, 2);

    expect($result['has_more'])->toBeTrue();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/users/octocat/following?per_page=100&page=2');
});

it('uses the page size when github omits pagination headers', function (int $count, bool $hasMore): void {
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);
    $people = array_map(fn (int $id): array => ['id' => $id], $count === 0 ? [] : range(1, $count));

    Http::fake(['api.github.com/users/octocat/following*' => Http::response($people)]);

    expect(resolve(GitHubFollowing::class)->getPage($user, 1)['has_more'])->toBe($hasMore);
})->with([[0, false], [99, false], [100, true]]);

it('uses complete pagination headers even for a full final page', function (): void {
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);

    Http::fake(['api.github.com/users/octocat/following*' => Http::response(
        array_map(fn (int $id): array => ['id' => $id], range(1, 100)),
        200,
        ['Link' => '<https://api.github.com/users/octocat/following?page=1>; rel="prev"'],
    )]);

    expect(resolve(GitHubFollowing::class)->getPage($user, 2)['has_more'])->toBeFalse();
});

it('authenticates only with complete nonempty oauth app credentials', function (?string $id, ?string $secret, bool $authenticated): void {
    config(['services.github.client_id' => $id, 'services.github.client_secret' => $secret]);
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);

    Http::fake(['api.github.com/users/octocat/following*' => Http::response([])]);

    resolve(GitHubFollowing::class)->getPage($user, 1);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization') === $authenticated);
})->with([[null, null, false], ['', '', false], ['client', '', false], ['client', 'secret', true]]);

it('retries transient github failures with bounded attempts', function (): void {
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);

    Http::fakeSequence('api.github.com/users/octocat/following*')
        ->push(['message' => 'Server Error'], 500)
        ->push([]);

    expect(resolve(GitHubFollowing::class)->getPage($user, 1)['github_ids'])->toBe([]);

    Http::assertSentCount(3);
});

it('does not retry a rate limited request immediately', function (): void {
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);

    Http::fake(['api.github.com/users/octocat/following*' => Http::response(['message' => 'Limit'], 403)]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage($user, 1))->toThrow(RequestException::class);

    Http::assertSentCount(2);
});

it('rejects account identity mismatches and malformed usernames before fetching following', function (array $account): void {
    $user = User::factory()->make(['github_id' => '42']);

    Http::fake(['api.github.com/user/42' => Http::response($account)]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage($user, 1))->toThrow(UnexpectedValueException::class);

    Http::assertSentCount(1);
})->with([
    'different owner' => [['id' => 99, 'login' => 'other']],
    'malformed owner id' => [['id' => [], 'login' => 'other']],
    'unsafe username' => [['id' => 42, 'login' => '../user']],
    'missing username' => [['id' => 42]],
]);

it('rejects a malformed owner response before reading following', function (): void {
    $user = User::factory()->make(['github_id' => '42']);

    Http::fake(['api.github.com/user/42' => Http::response('null')]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage($user, 1))->toThrow(UnexpectedValueException::class);

    Http::assertSentCount(1);
});

it('rejects malformed following data instead of caching an empty success', function (array $people): void {
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);

    Http::fake(['api.github.com/users/octocat/following*' => Http::response($people)]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage($user, 1))->toThrow(UnexpectedValueException::class);
})->with([
    'object' => [['message' => 'broken']],
    'missing id' => [[['login' => 'friend']]],
    'invalid id' => [[['id' => '1 OR 1=1']]],
]);

function fakeGitHubFollowingOwner(User $user): void
{
    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['id' => $user->github_id, 'login' => 'octocat']),
    ]);
}
