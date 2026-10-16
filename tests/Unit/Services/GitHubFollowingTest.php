<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\GitHubFollowing;
use Illuminate\Http\Client\ConnectionException;
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

    $following = resolve(GitHubFollowing::class);
    $username = $following->resolveUsername($user);
    $result = $following->getPage($username, 1);

    expect($username)->toBe('current-name');
    expect($result)->toBe(['github_ids' => ['123'], 'has_more' => false]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/users/current-name/following?per_page=100&page=1');
});

it('requests later pages on the fixed github endpoint without following pagination links', function (): void {
    Http::fake([
        'api.github.com/users/octocat/following*' => Http::response([['id' => 1]], 200, [
            'Link' => '<https://elsewhere.test/steal>; rel="next"',
        ]),
    ]);

    $result = resolve(GitHubFollowing::class)->getPage('octocat', 2);

    expect($result['has_more'])->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/users/octocat/following?per_page=100&page=2');
});

it('reuses the resolved username across pages without another owner request', function (): void {
    $user = User::factory()->make(['github_id' => '42']);

    Http::fake([
        'api.github.com/user/42' => Http::sequence()->push(['id' => 42, 'login' => 'octocat']),
        'api.github.com/users/octocat/following*' => Http::response([]),
    ]);
    $following = resolve(GitHubFollowing::class);
    $username = $following->resolveUsername($user);

    $following->getPage($username, 1);
    $following->getPage($username, 2);

    Http::assertSentCount(3);
});

it('uses the page size when github omits pagination headers', function (int $count, bool $hasMore): void {
    $people = array_map(fn (int $id): array => ['id' => $id], $count === 0 ? [] : range(1, $count));

    Http::fake(['api.github.com/users/octocat/following*' => Http::response($people)]);

    expect(resolve(GitHubFollowing::class)->getPage('octocat', 1)['has_more'])->toBe($hasMore);
})->with([[0, false], [99, false], [100, true]]);

it('uses complete pagination headers even for a full final page', function (): void {
    Http::fake(['api.github.com/users/octocat/following*' => Http::response(
        array_map(fn (int $id): array => ['id' => $id], range(1, 100)),
        200,
        ['Link' => '<https://api.github.com/users/octocat/following?page=1>; rel="prev"'],
    )]);

    expect(resolve(GitHubFollowing::class)->getPage('octocat', 2)['has_more'])->toBeFalse();
});

it('authenticates only with complete nonempty oauth app credentials', function (?string $id, ?string $secret, bool $authenticated): void {
    config(['services.github.client_id' => $id, 'services.github.client_secret' => $secret]);
    $user = User::factory()->make();
    fakeGitHubFollowingOwner($user);

    Http::fake(['api.github.com/users/octocat/following*' => Http::response([])]);

    $following = resolve(GitHubFollowing::class);
    $following->getPage($following->resolveUsername($user), 1);

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization') !== $authenticated);
})->with([[null, null, false], ['', '', false], ['client', '', false], ['client', 'secret', true]]);

it('retries transient github failures with bounded attempts', function (): void {
    Http::fakeSequence('api.github.com/users/octocat/following*')
        ->push(['message' => 'Server Error'], 500)
        ->push([]);

    expect(resolve(GitHubFollowing::class)->getPage('octocat', 1)['github_ids'])->toBe([]);

    Http::assertSentCount(2);
});

it('stops after three failed github server requests', function (): void {
    Http::fake(['api.github.com/users/octocat/following*' => Http::response(['message' => 'Server Error'], 500)]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage('octocat', 1))->toThrow(RequestException::class);

    Http::assertSentCount(3);
});

it('stops after three failed github connection attempts', function (): void {
    Http::fake(['api.github.com/users/octocat/following*' => Http::failedConnection()]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage('octocat', 1))->toThrow(ConnectionException::class);

    Http::assertSentCount(3);
});

it('does not retry a rate limited request immediately', function (): void {
    Http::fake(['api.github.com/users/octocat/following*' => Http::response(['message' => 'Limit'], 403)]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage('octocat', 1))->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

it('rejects following redirects without requesting their destination', function (int $status): void {
    Http::fake([
        'api.github.com/users/octocat/following*' => function (Request $request, array $options) use ($status) {
            expect($options['allow_redirects'])->toBeFalse();

            return Http::response([], $status, ['Location' => 'https://untrusted.example/following']);
        },
    ]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage('octocat', 1))->toThrow(RequestException::class);

    Http::assertSentCount(1);
})->with([301, 302, 307, 308]);

it('rejects unsafe usernames and invalid pages before sending a following request', function (string $username, int $page): void {
    expect(fn () => resolve(GitHubFollowing::class)->getPage($username, $page))->toThrow(UnexpectedValueException::class);

    Http::assertNothingSent();
})->with([
    'unsafe username' => ['../user', 1],
    'empty username' => ['', 1],
    'zero page' => ['octocat', 0],
    'negative page' => ['octocat', -1],
]);

it('rejects account identity mismatches and malformed usernames before fetching following', function (array $account): void {
    $user = User::factory()->make(['github_id' => '42']);

    Http::fake(['api.github.com/user/42' => Http::response($account)]);

    expect(fn () => resolve(GitHubFollowing::class)->resolveUsername($user))->toThrow(UnexpectedValueException::class);

    Http::assertSentCount(1);
})->with([
    'different owner' => [['id' => 99, 'login' => 'other']],
    'malformed owner id' => [['id' => [], 'login' => 'other']],
    'missing owner id' => [['login' => 'octocat']],
    'unsafe username' => [['id' => 42, 'login' => '../user']],
    'missing username' => [['id' => 42]],
]);

it('rejects a malformed owner response before reading following', function (): void {
    $user = User::factory()->make(['github_id' => '42']);

    Http::fake(['api.github.com/user/42' => Http::response('null')]);

    expect(fn () => resolve(GitHubFollowing::class)->resolveUsername($user))->toThrow(UnexpectedValueException::class);

    Http::assertSentCount(1);
});

it('rejects malformed following data instead of caching an empty success', function (array $people): void {
    Http::fake(['api.github.com/users/octocat/following*' => Http::response($people)]);

    expect(fn () => resolve(GitHubFollowing::class)->getPage('octocat', 1))->toThrow(UnexpectedValueException::class);
})->with([
    'object' => [['message' => 'broken']],
    'missing id' => [[['login' => 'friend']]],
    'invalid id' => [[['id' => '1 OR 1=1']]],
    'oversized page' => [array_map(fn (int $id): array => ['id' => $id], range(1, 101))],
]);

function fakeGitHubFollowingOwner(User $user): void
{
    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['id' => $user->github_id, 'login' => 'octocat']),
    ]);
}
