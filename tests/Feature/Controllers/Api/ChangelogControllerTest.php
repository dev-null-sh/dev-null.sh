<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;

/**
 * @return array<string, mixed>
 */
function apiChangelogCommit(int $number, string $committedAt, ?int $parent): array
{
    return [
        'sha' => "c897f867{$number}",
        'message' => "feat: turn emoji shortcodes into emoji in remotes (#{$number})",
        'url' => "https://github.com/dev-null-sh/dev-null.sh/commit/c897f867{$number}",
        'committed_at' => $committedAt,
        'parents' => $parent === null ? [] : ["c897f867{$parent}"],
        'author' => ['id' => 14284867, 'login' => 'xHeaven', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4'],
    ];
}

function mockApiChangelog(): void
{
    test()->mock(GitHubProvider::class)->shouldReceive('commits')
        ->andReturnUsing(fn (string $repository, string $branch, int $page): array => $page === 1 ? [
            apiChangelogCommit(377, '2026-10-17T23:30:00Z', 376),
            apiChangelogCommit(376, '2026-10-16T12:00:00Z', 375),
            apiChangelogCommit(375, '2026-10-15T12:00:00Z', 374),
            apiChangelogCommit(374, '2026-10-14T12:00:00Z', null),
        ] : []);
}

it('lists the latest days of commits on production, signed or not, with a link to every commit on production', function (bool $isSigned): void {
    mockApiChangelog();
    $uri = route('api-changelog.index', ['timezone' => 'UTC']);

    $response = $isSigned ? signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', $uri) : $this->getJson($uri);

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.sha', 'c897f867377')
        ->assertJsonPath('data.0.pull_request.number', 377)
        ->assertJsonPath('data.0.type', 'feat')
        ->assertJsonPath('data.0.title', 'Turn emoji shortcodes into emoji in remotes')
        ->assertJsonPath('data.0.author.login', 'xHeaven')
        ->assertJsonPath('older_before', '2026-10-15T00:00:00+00:00')
        ->assertJsonPath('commits_url', 'https://github.com/dev-null-sh/dev-null.sh/commits/production');
})->with(['unsigned' => false, 'signed' => true]);

it('groups days in the reader’s time zone, and carries on from where the reader is', function (): void {
    mockApiChangelog();

    $this->getJson(route('api-changelog.index', ['timezone' => 'Europe/Budapest']))
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.2.pull_request.number', 375);

    $this->getJson(route('api-changelog.index', ['timezone' => 'UTC', 'before' => '2026-10-15T00:00:00+00:00']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.pull_request.number', 374)
        ->assertJsonPath('older_before', null);
});

it('uses the signed user’s time zone when the request names none', function (): void {
    mockApiChangelog();
    $keyPair = apiKeyPairFor(User::factory()->create(['timezone' => 'Europe/Budapest']));

    signedApiRequest($keyPair, 'GET', route('api-changelog.index'))
        ->assertJsonPath('older_before', '2026-10-14T22:00:00+00:00');

    signedApiRequest($keyPair, 'GET', route('api-changelog.index', ['timezone' => 'UTC']))
        ->assertJsonPath('older_before', '2026-10-15T00:00:00+00:00');
});

it('falls back to the app’s time zone for a signed user who saved none', function (): void {
    mockApiChangelog();
    config(['app.timezone' => 'Europe/Budapest']);

    signedApiRequest(apiKeyPairFor(User::factory()->create(['timezone' => null])), 'GET', route('api-changelog.index'))
        ->assertJsonPath('older_before', '2026-10-14T22:00:00+00:00');
});

it('rejects a time zone or a starting point it cannot read', function (array $query, string $field): void {
    $this->getJson(route('api-changelog.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown time zone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'not a date' => [['timezone' => 'UTC', 'before' => 'yesterday-ish'], 'before'],
]);

it('says the changelog is unavailable when GitHub cannot be reached', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->andThrow(new ConnectionException('timed out'));

    $this->getJson(route('api-changelog.index', ['timezone' => 'UTC']))
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'changelog_unavailable');
});
