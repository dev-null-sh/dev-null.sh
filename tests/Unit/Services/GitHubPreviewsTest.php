<?php

declare(strict_types=1);

use App\Dto\GitHubReference;
use App\Models\User;
use App\Services\GitHubPreviews;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function describeOnGitHub(string $url): ?array
{
    $reference = GitHubReference::tryFromUrl($url);

    assert($reference instanceof GitHubReference);

    return resolve(GitHubPreviews::class)->describe($reference);
}

it('describes each kind of reference', function (string $url, array $responses, ?string $title, array $github): void {
    Http::fake(array_map(fn (array|string $body): PromiseInterface => Http::response($body), $responses));

    expect(describeOnGitHub($url))->toBe([
        'title' => $title,
        'description' => null,
        'image_url' => null,
        'site_name' => 'GitHub',
        'github' => $github,
    ]);
})->with([
    'repository' => [
        'https://github.com/laravel/framework',
        ['api.github.com/repos/laravel/framework' => ['description' => 'The Laravel Framework.', 'stargazers_count' => 34120, 'language' => 'PHP']],
        'The Laravel Framework.',
        ['type' => 'repository', 'label' => 'laravel/framework', 'state' => null, 'stars' => 34120, 'language' => 'PHP'],
    ],
    'repository without a description' => [
        'https://github.com/laravel/framework',
        ['api.github.com/repos/laravel/framework' => ['description' => null, 'stargazers_count' => 0, 'language' => null]],
        'laravel/framework',
        ['type' => 'repository', 'label' => 'laravel/framework', 'state' => null, 'stars' => 0, 'language' => null],
    ],
    'open issue' => [
        'https://github.com/nunomaduro/devnull.com/issues/21',
        ['api.github.com/repos/nunomaduro/devnull.com/issues/21' => ['title' => 'Add follower page', 'state' => 'open', 'user' => ['login' => 'brendt', 'id' => 6905297]]],
        'Add follower page',
        ['type' => 'issue', 'label' => 'nunomaduro/devnull.com#21', 'state' => 'open', 'stars' => null, 'language' => null, 'author' => 'brendt', 'author_on_devnull' => false],
    ],
    'merged pull request' => [
        'https://github.com/nunomaduro/devnull.com/pull/14',
        ['api.github.com/repos/nunomaduro/devnull.com/pulls/14' => ['title' => 'feat: commit graph frontend', 'state' => 'closed', 'merged_at' => '2026-10-15T12:00:00Z', 'user' => ['login' => 'xHeaven', 'id' => 14284867], 'additions' => 2038, 'deletions' => 1143, 'changed_files' => 51]],
        'feat: commit graph frontend',
        ['type' => 'pull_request', 'label' => 'nunomaduro/devnull.com#14', 'state' => 'merged', 'stars' => null, 'language' => null, 'author' => 'xHeaven', 'author_on_devnull' => false, 'additions' => 2038, 'deletions' => 1143, 'files' => 51],
    ],
    'closed pull request' => [
        'https://github.com/nunomaduro/devnull.com/pull/10',
        ['api.github.com/repos/nunomaduro/devnull.com/pulls/10' => ['title' => 'refactor: eager load commit author', 'state' => 'closed', 'merged_at' => null, 'additions' => 3, 'deletions' => 1, 'changed_files' => 1]],
        'refactor: eager load commit author',
        ['type' => 'pull_request', 'label' => 'nunomaduro/devnull.com#10', 'state' => 'closed', 'stars' => null, 'language' => null, 'additions' => 3, 'deletions' => 1, 'files' => 1],
    ],
    'issue number that is a pull request' => [
        'https://github.com/nunomaduro/devnull.com/issues/14',
        [
            'api.github.com/repos/nunomaduro/devnull.com/issues/14' => ['title' => 'feat: commit graph frontend', 'state' => 'closed', 'pull_request' => ['merged_at' => '2026-10-15T12:00:00Z']],
            'api.github.com/repos/nunomaduro/devnull.com/pulls/14' => ['title' => 'feat: commit graph frontend', 'state' => 'closed', 'merged_at' => '2026-10-15T12:00:00Z', 'additions' => 2038, 'deletions' => 1143, 'changed_files' => 51],
        ],
        'feat: commit graph frontend',
        ['type' => 'pull_request', 'label' => 'nunomaduro/devnull.com#14', 'state' => 'merged', 'stars' => null, 'language' => null, 'additions' => 2038, 'deletions' => 1143, 'files' => 51],
    ],
    'release' => [
        'https://github.com/laravel/framework/releases/tag/v13.0.0',
        ['api.github.com/repos/laravel/framework/releases/tags/v13.0.0' => ['name' => 'Laravel 13', 'tag_name' => 'v13.0.0', 'author' => ['login' => 'taylorotwell', 'id' => 463230]]],
        'Laravel 13',
        ['type' => 'release', 'label' => 'laravel/framework@v13.0.0', 'state' => 'released', 'stars' => null, 'language' => null, 'author' => 'taylorotwell', 'author_on_devnull' => false],
    ],
    'release published by a bot' => [
        'https://github.com/laravel/framework/releases/tag/v13.0.0',
        ['api.github.com/repos/laravel/framework/releases/tags/v13.0.0' => ['name' => 'v13.0.0', 'author' => ['login' => 'github-actions[bot]', 'id' => 41898282, 'type' => 'Bot']]],
        'v13.0.0',
        ['type' => 'release', 'label' => 'laravel/framework@v13.0.0', 'state' => 'released', 'stars' => null, 'language' => null],
    ],
    'release without a name' => [
        'https://github.com/laravel/framework/releases/tag/v13.0.0',
        ['api.github.com/repos/laravel/framework/releases/tags/v13.0.0' => ['name' => '', 'tag_name' => 'v13.0.0']],
        'v13.0.0',
        ['type' => 'release', 'label' => 'laravel/framework@v13.0.0', 'state' => 'released', 'stars' => null, 'language' => null],
    ],
    'commit' => [
        'https://github.com/nunomaduro/devnull.com/commit/42faad4',
        ['api.github.com/repos/nunomaduro/devnull.com/commits/42faad4*' => [
            'commit' => ['message' => "feat: add opt-in local auto-login\n\nSigns in as a local user."],
            'author' => ['login' => 'MrPunyapal', 'id' => 53343069],
            'stats' => ['additions' => 113, 'deletions' => 1],
        ]],
        'feat: add opt-in local auto-login',
        ['type' => 'commit', 'label' => 'nunomaduro/devnull.com@42faad4', 'state' => null, 'stars' => null, 'language' => null, 'author' => 'MrPunyapal', 'author_on_devnull' => false, 'additions' => 113, 'deletions' => 1],
    ],
    'lines of code' => [
        'https://github.com/nunomaduro/devnull.com/blob/production/app/Actions/CreateCommit.php#L2-L3',
        ['api.github.com/repos/nunomaduro/devnull.com/contents/app/Actions/CreateCommit.php*' => "<?php\n\ndeclare(strict_types=1);   \n\nnamespace App\\Actions;"],
        'app/Actions/CreateCommit.php#L2-L3',
        ['type' => 'code', 'label' => 'nunomaduro/devnull.com', 'state' => null, 'stars' => null, 'language' => 'php', 'lines' => ['', 'declare(strict_types=1);'], 'start' => 2],
    ],
]);

it('links authors who are on devnull too', function (): void {
    User::factory()->create(['github_id' => '6905297']);

    Http::fake(['api.github.com/repos/nunomaduro/devnull.com/issues/21' => Http::response(['title' => 'Add follower page', 'state' => 'open', 'user' => ['login' => 'brendt', 'id' => 6905297]])]);

    expect(describeOnGitHub('https://github.com/nunomaduro/devnull.com/issues/21')['github'] ?? [])
        ->toMatchArray(['author' => 'brendt', 'author_on_devnull' => true]);
});

it('leaves out the size of a pull request found by its issue number when github does not return it', function (PromiseInterface|Closure $pullRequest): void {
    Http::fake([
        'api.github.com/repos/nunomaduro/devnull.com/issues/14' => Http::response(['title' => 'feat: commit graph frontend', 'state' => 'open', 'pull_request' => ['merged_at' => null]]),
        'api.github.com/repos/nunomaduro/devnull.com/pulls/14' => $pullRequest,
    ]);

    expect(describeOnGitHub('https://github.com/nunomaduro/devnull.com/issues/14')['github'] ?? [])
        ->toMatchArray(['type' => 'pull_request', 'state' => 'open'])
        ->not->toHaveKeys(['additions', 'deletions', 'files']);
})->with([
    'server error' => fn (): PromiseInterface => Http::response(status: 500),
    'connection failure' => fn (): Closure => Http::failedConnection(),
]);

it('asks github once about a link to a pull request', function (): void {
    Http::fake(['api.github.com/repos/nunomaduro/devnull.com/pulls/14' => Http::response(['title' => 'feat: commit graph frontend', 'state' => 'open', 'merged_at' => null])]);

    describeOnGitHub('https://github.com/nunomaduro/devnull.com/pull/14');

    Http::assertSentCount(1);
});

it('asks for a single file of a commit, since its stats cover all of them', function (): void {
    Http::fake(['api.github.com/repos/nunomaduro/devnull.com/commits/42faad4*' => Http::response(['commit' => ['message' => 'feat: add opt-in local auto-login'], 'stats' => ['additions' => 113, 'deletions' => 1]])]);

    describeOnGitHub('https://github.com/nunomaduro/devnull.com/commit/42faad4');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'per_page=1'));
});

it('reads at most twelve lines of code at the linked ref', function (): void {
    Http::fake(['api.github.com/repos/laravel/framework/contents/src/Foo.php*' => Http::response(implode("\n", array_map(fn (int $line): string => "line {$line}", range(1, 40))))]);

    $metadata = describeOnGitHub('https://github.com/laravel/framework/blob/13.x/src/Foo.php#L5-L30');

    expect($metadata['github']['lines'] ?? [])->toHaveCount(12)
        ->and($metadata['github']['lines'][0] ?? null)->toBe('line 5')
        ->and($metadata['github']['start'] ?? null)->toBe(5);

    Http::assertSent(fn (Request $request): bool => $request['ref'] === '13.x'
        && $request->hasHeader('Accept', 'application/vnd.github.raw+json'));
});

it('truncates a long title to fit the title column', function (): void {
    Http::fake(['api.github.com/repos/laravel/framework/issues/1' => Http::response(['title' => str_repeat('a', 400), 'state' => 'open'])]);

    expect(describeOnGitHub('https://github.com/laravel/framework/issues/1')['title'] ?? null)
        ->toHaveLength(300)
        ->toEndWith('…');
});

it('returns nothing when github cannot describe the reference', function (string $url, string $endpoint, int $status): void {
    Http::fake([$endpoint => Http::response(['message' => 'Not Found'], $status)]);

    expect(describeOnGitHub($url))->toBeNull();
})->with([
    'unknown issue' => ['https://github.com/acme/does-not-exist/issues/1', 'api.github.com/repos/acme/does-not-exist/issues/1', 404],
    'forbidden' => ['https://github.com/acme/does-not-exist/issues/1', 'api.github.com/repos/acme/does-not-exist/issues/1', 403],
    'unknown file' => ['https://github.com/acme/repo/blob/main/missing.php#L1', 'api.github.com/repos/acme/repo/contents/missing.php*', 404],
]);

it('throws when github fails in a way that may pass, so the job tries again', function (string $url, string $endpoint, PromiseInterface|Closure $response): void {
    Http::fake([$endpoint => $response]);

    describeOnGitHub($url);
})->throws(HttpClientException::class)->with([
    'server error' => ['https://github.com/acme/repo/issues/1', 'api.github.com/repos/acme/repo/issues/1', fn (): PromiseInterface => Http::response(status: 500)],
    'too many requests' => ['https://github.com/acme/repo/issues/1', 'api.github.com/repos/acme/repo/issues/1', fn (): PromiseInterface => Http::response(status: 429)],
    'rate limited' => ['https://github.com/acme/repo/issues/1', 'api.github.com/repos/acme/repo/issues/1', fn (): PromiseInterface => Http::response(status: 403, headers: ['X-RateLimit-Remaining' => '0'])],
    'connection failure' => ['https://github.com/acme/repo/issues/1', 'api.github.com/repos/acme/repo/issues/1', fn (): Closure => Http::failedConnection()],
    'server error on a file' => ['https://github.com/acme/repo/blob/main/src.php#L1', 'api.github.com/repos/acme/repo/contents/src.php*', fn (): PromiseInterface => Http::response(status: 500)],
]);

it('returns nothing for lines past the end of the file', function (): void {
    Http::fake(['api.github.com/repos/acme/repo/contents/short.php*' => Http::response("<?php\n")]);

    expect(describeOnGitHub('https://github.com/acme/repo/blob/main/short.php#L50'))->toBeNull();
});

it('reads no more than a million characters of a file', function (): void {
    Http::fake(['api.github.com/repos/acme/repo/contents/huge.txt*' => fn (): PromiseInterface => Http::response(str_repeat(str_repeat('a', 99)."\n", 20_000))]);

    expect(describeOnGitHub('https://github.com/acme/repo/blob/main/huge.txt#L15000'))->toBeNull()
        ->and(describeOnGitHub('https://github.com/acme/repo/blob/main/huge.txt#L10'))->not->toBeNull();
});
