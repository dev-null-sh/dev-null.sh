<?php

declare(strict_types=1);

use App\Services\GitHubProviders\HttpGitHubProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config(['services.github.client_id' => 'client-id', 'services.github.client_secret' => 'client-secret']);
});

it('calls the API as the OAuth app', function (): void {
    Http::fake(['api.github.com/user/583231' => Http::response(['login' => 'octocat'])]);

    expect(new HttpGitHubProvider()->login('583231'))->toBe('octocat');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/user/583231'
        && $request->header('Accept') === ['application/vnd.github+json']
        && $request->header('X-GitHub-Api-Version') === ['2022-11-28']
        && $request->header('Authorization') === ['Basic '.base64_encode('client-id:client-secret')]);
});

it('downloads an avatar', function (): void {
    Http::fake(['avatars.githubusercontent.com/*' => Http::response('image-bytes')]);

    expect(new HttpGitHubProvider()->avatar('https://avatars.githubusercontent.com/u/583231'))->toBe('image-bytes');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://avatars.githubusercontent.com/u/583231');
});

it('has no avatar when it cannot be downloaded', function (Closure|PromiseInterface $response): void {
    Http::fake(['avatars.githubusercontent.com/*' => $response]);

    expect(new HttpGitHubProvider()->avatar('https://avatars.githubusercontent.com/u/583231'))->toBeNull();
})->with([
    'an error' => fn (): PromiseInterface => Http::response('', 500),
    'not found' => fn (): PromiseInterface => Http::response('', 404),
    'unreachable' => fn (): Closure => Http::failedConnection(),
]);

it('reads the core rate limit', function (): void {
    $resetsAt = now()->addMinutes(30);
    Http::fake(['api.github.com/rate_limit' => Http::response([
        'resources' => ['core' => ['limit' => 5000, 'remaining' => 4321, 'reset' => $resetsAt->getTimestamp()]],
    ])]);

    $rateLimit = new HttpGitHubProvider()->rateLimit();

    expect($rateLimit['remaining'])->toBe(4321)
        ->and($rateLimit['limit'])->toBe(5000)
        ->and($rateLimit['resets_at']->getTimestamp())->toBe($resetsAt->getTimestamp());
});

it('looks accounts up by id or login, and is null for one that no longer exists', function (): void {
    Http::fake([
        'api.github.com/user/583231' => Http::response(['login' => 'octocat', 'type' => 'User']),
        'api.github.com/users/brefphp' => Http::response(['login' => 'brefphp', 'type' => 'Organization']),
        'api.github.com/user/1' => Http::response(['message' => 'Not Found'], 404),
        'api.github.com/users/ghost' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $provider = new HttpGitHubProvider();

    expect($provider->account('583231'))->toBe(['login' => 'octocat', 'type' => 'User'])
        ->and($provider->accountByLogin('brefphp'))->toBe(['login' => 'brefphp', 'type' => 'Organization'])
        ->and($provider->account('1'))->toBeNull()
        ->and($provider->accountByLogin('ghost'))->toBeNull()
        ->and($provider->login('1'))->toBeNull();
});

it('fails when GitHub does', function (): void {
    Http::fake(['api.github.com/*' => Http::response([], 502)]);

    new HttpGitHubProvider()->account('583231');
})->throws(RequestException::class);

it('says whether the user shows their membership of the organization, by their current login', function (int $status, bool $expected): void {
    Http::fake([
        'api.github.com/user/720328' => Http::response(['id' => 720328, 'login' => 'mnapoli']),
        'api.github.com/organizations/45861341/public_members/mnapoli' => Http::response(null, $status),
    ]);

    expect(new HttpGitHubProvider()->isPublicMember('45861341', '720328'))->toBe($expected);
})->with([
    'a public member' => [204, true],
    'not a public member' => [404, false],
]);

it('does not list users GitHub no longer has as public members', function (): void {
    Http::fake(['api.github.com/user/720328' => Http::response(['message' => 'Not Found'], 404)]);

    expect(new HttpGitHubProvider()->isPublicMember('45861341', '720328'))->toBeFalse();

    Http::assertSentCount(1);
});

it('fails when GitHub fails to say whether the user is a public member', function (): void {
    Http::fake([
        'api.github.com/user/720328' => Http::response(['id' => 720328, 'login' => 'mnapoli']),
        'api.github.com/organizations/45861341/public_members/mnapoli' => Http::response([], 502),
    ]);

    new HttpGitHubProvider()->isPublicMember('45861341', '720328');
})->throws(RequestException::class);

it('asks GraphQL with the user token whether they have a sponsors listing', function (bool $hasSponsorsListing): void {
    Log::spy();
    Http::fake(['api.github.com/graphql' => Http::response(['data' => ['viewer' => ['hasSponsorsListing' => $hasSponsorsListing]]])]);

    expect(new HttpGitHubProvider()->hasSponsorsListing('gho_octocat'))->toBe($hasSponsorsListing);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/graphql'
        && $request->hasHeader('Authorization', 'Bearer gho_octocat')
        && $request['query'] === 'query { viewer { hasSponsorsListing } }');
    Log::shouldNotHaveReceived('warning');
})->with([true, false]);

it('cannot say whether there is a sponsors listing when GitHub does not answer', function (Closure $response, array $context): void {
    Log::spy();
    Http::fake(['api.github.com/graphql' => $response]);

    expect(new HttpGitHubProvider()->hasSponsorsListing('gho_octocat'))->toBeNull();

    Log::shouldHaveReceived('warning')->once()->with('Could not check the GitHub Sponsors listing.', $context);
})->with([
    'server error' => [fn () => Http::response(status: 502), ['status' => 502, 'errors' => null]],
    'graphql error' => [fn () => Http::response(['errors' => [['type' => 'INSUFFICIENT_SCOPES']]]), ['status' => 200, 'errors' => [['type' => 'INSUFFICIENT_SCOPES']]]],
    'connection failure' => [fn () => throw new ConnectionException('timeout'), ['error' => 'timeout']],
]);

it('reads the ids of followed and following accounts a page at a time, up to the given number of pages', function (): void {
    $page = fn (int $from, int $count): array => array_map(fn (int $id): array => ['id' => $id, 'login' => "user{$id}"], range($from, $from + $count - 1));

    Http::fake([
        'api.github.com/users/octocat/following?per_page=100&page=1' => Http::response($page(1, 100)),
        'api.github.com/users/octocat/following?per_page=100&page=2' => Http::response($page(101, 100)),
        'api.github.com/users/octocat/followers?per_page=100&page=1' => Http::response($page(1, 100)),
        'api.github.com/users/octocat/followers?per_page=100&page=2' => Http::response($page(101, 3)),
    ]);

    $provider = new HttpGitHubProvider();

    expect($provider->followingIds('octocat', 1))->toBe(array_map(strval(...), range(1, 100)))
        ->and($provider->followerIds('octocat', 10))->toBe(array_map(strval(...), range(1, 103)));

    Http::assertSentCount(3);
});

it('reads the authentication and signing keys of the account', function (): void {
    Http::fake([
        'api.github.com/users/octocat/keys*' => Http::response([['id' => 1, 'key' => 'ssh-ed25519 AAAA1']]),
        'api.github.com/users/octocat/ssh_signing_keys*' => Http::response([['id' => 2, 'key' => 'ssh-ed25519 AAAA2']]),
    ]);

    expect(new HttpGitHubProvider()->sshKeys('octocat'))->toBe(['ssh-ed25519 AAAA1', 'ssh-ed25519 AAAA2']);

    Http::assertSent(fn (Request $request): bool => $request['per_page'] === 100);
});

it('reads the profile readme as html, and is null when there is none', function (): void {
    Http::fake([
        'api.github.com/repos/octocat/octocat/readme' => Http::response('<p>Hi</p>'),
        'api.github.com/repos/ghost/ghost/readme' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $provider = new HttpGitHubProvider();

    expect($provider->readme('octocat'))->toBe('<p>Hi</p>')
        ->and($provider->readme('ghost'))->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->header('Accept') === ['application/vnd.github.html+json']);
});

it('reads a page of the closed pull requests into the branch, most recently updated first', function (): void {
    Http::fake(['api.github.com/repos/dev-null-sh/dev-null.sh/pulls*' => Http::response([['number' => 1]])]);

    expect(new HttpGitHubProvider()->closedPullRequests('dev-null-sh/dev-null.sh', 'production', 2, 100))->toBe([['number' => 1]]);

    Http::assertSent(fn (Request $request): bool => $request['state'] === 'closed'
        && $request['base'] === 'production'
        && $request['sort'] === 'updated'
        && $request['direction'] === 'desc'
        && $request['per_page'] === 100
        && $request['page'] === 2);
});

it('reads a page of the contributors', function (): void {
    Http::fake(['api.github.com/repos/dev-null-sh/dev-null.sh/contributors*' => Http::response([['login' => 'octocat']])]);

    expect(new HttpGitHubProvider()->contributors('dev-null-sh/dev-null.sh', 3, 100))->toBe([['login' => 'octocat']]);

    Http::assertSent(fn (Request $request): bool => $request['per_page'] === 100 && $request['page'] === 3);
});

it('reads an issue or pull request without its body, and is null when there is none', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::response([
            'number' => 722, 'title' => 'Link PRs', 'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/722', 'body' => 'Not needed',
        ]),
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/1' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $provider = new HttpGitHubProvider();

    expect($provider->issue('dev-null-sh/dev-null.sh', 722))->toBe(['number' => 722, 'title' => 'Link PRs', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/722'])
        ->and($provider->issue('dev-null-sh/dev-null.sh', 1))->toBeNull();
});

describe('issues', function (): void {
    beforeEach(function (): void {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($key !== false);
        openssl_pkey_export($key, $privateKey);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        config([
            'services.github.repository' => 'example/upstream',
            'services.github_app' => ['id' => '12345', 'private_key' => str_replace("\n", '\n', $privateKey)],
        ]);

        Http::fake([
            'api.github.com/repos/example/upstream/installation' => Http::response(['id' => 99]),
            'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
            'api.github.com/repos/example/upstream/issues' => Http::response(['number' => 123, 'html_url' => 'https://github.com/example/upstream/issues/123'], 201),
        ]);
    });

    it('creates the issue as the app, with a token limited to writing issues on this repository', function (): void {
        expect(new HttpGitHubProvider()->createIssue('Broken search', 'It is broken.', ['bug']))
            ->toBe(['number' => 123, 'url' => 'https://github.com/example/upstream/issues/123']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.github.com/app/installations/99/access_tokens') {
                return false;
            }

            $jwt = JWT::decode(mb_substr($request->header('Authorization')[0], 7), new Key($this->publicKey, 'RS256'));

            return $jwt->iss === '12345'
                && $request['repositories'] === ['upstream']
                && $request['permissions'] === ['issues' => 'write'];
        });
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/example/upstream/issues'
            && $request->hasHeader('Authorization', 'Bearer installation-token')
            && $request['title'] === 'Broken search'
            && $request['body'] === 'It is broken.'
            && $request['labels'] === ['bug']);
    });

    it('reuses the installation token across issues', function (): void {
        $provider = new HttpGitHubProvider();

        $provider->createIssue('First', 'First.', ['bug']);
        $provider->createIssue('Second', 'Second.', ['bug']);

        Http::assertSentCount(4);
    });
});

it('searches issues and commits, sorting only when asked', function (): void {
    Http::fake([
        'api.github.com/search/issues*' => Http::response(['incomplete_results' => false, 'total_count' => 7, 'items' => [['number' => 1]]]),
        'api.github.com/search/commits*' => Http::response(['incomplete_results' => true, 'total_count' => 1, 'items' => []]),
    ]);

    $provider = new HttpGitHubProvider();

    expect($provider->searchIssues('repo:a/b is:open', 10, 'updated'))->toBe(['incomplete' => false, 'total' => 7, 'items' => [['number' => 1]]])
        ->and($provider->searchIssues('repo:a/b is:pr', 1, null))->toBe(['incomplete' => false, 'total' => 7, 'items' => [['number' => 1]]])
        ->and($provider->searchCommits('repo:a/b merge:false', 5))->toBe(['incomplete' => true, 'total' => 1, 'items' => []]);

    Http::assertSent(fn (Request $request): bool => $request['q'] === 'repo:a/b is:open' && $request['per_page'] === 10 && $request['sort'] === 'updated' && $request['order'] === 'desc');
    Http::assertSent(fn (Request $request): bool => $request['q'] === 'repo:a/b is:pr' && $request['per_page'] === 1 && ! isset($request['sort']));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'search/commits') && $request['q'] === 'repo:a/b merge:false' && $request['per_page'] === 5);
});

it('reads the stars, forks and languages of the repository', function (): void {
    Http::fake([
        'api.github.com/repos/a/b' => Http::response(['stargazers_count' => 42, 'forks_count' => 7]),
        'api.github.com/repos/a/b/languages' => Http::response(['PHP' => 1000, 'TypeScript' => 500]),
    ]);

    $provider = new HttpGitHubProvider();

    expect($provider->repository('a/b'))->toBe(['stars' => 42, 'forks' => 7])
        ->and($provider->languages('a/b'))->toBe(['PHP' => 1000, 'TypeScript' => 500]);
});
