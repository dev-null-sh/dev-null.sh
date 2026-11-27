<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Inertia\Testing\AssertableInertia;

/**
 * @return array<string, mixed>
 */
function changelogControllerCommit(int $number, string $committedAt, ?int $parent): array
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

/**
 * @return list<array<string, mixed>>
 */
function changelogPage(int $page): array
{
    return $page === 1 ? [
        changelogControllerCommit(377, '2026-10-17T23:30:00Z', 376),
        changelogControllerCommit(376, '2026-10-16T12:00:00Z', 375),
        changelogControllerCommit(375, '2026-10-15T12:00:00Z', 374),
        changelogControllerCommit(374, '2026-10-14T12:00:00Z', null),
    ] : [];
}

it('lists the latest days of commits on production for anyone, with a link to every commit on production', function (bool $isSignedIn): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')
        ->andReturnUsing(fn (string $repository, string $branch, int $page): array => changelogPage($page));

    $request = $isSignedIn ? $this->actingAs(User::factory()->create()) : $this;

    $request->getJson('/changelog?timezone=UTC')
        ->assertOk()
        ->assertJsonPath('commitsUrl', 'https://github.com/dev-null-sh/dev-null.sh/commits/production')
        ->assertJsonPath('commits.0.sha', 'c897f867377')
        ->assertJsonPath('commits.0.pull_request.number', 377)
        ->assertJsonPath('commits.0.type', 'feat')
        ->assertJsonPath('commits.0.title', 'Turn emoji shortcodes into emoji in remotes')
        ->assertJsonPath('commits.0.author.login', 'xHeaven')
        ->assertJsonCount(3, 'commits')
        ->assertJsonPath('olderBefore', '2026-10-15T00:00:00+00:00');
})->with(['guest' => false, 'signed in' => true]);

it('groups days in the reader’s time zone, and carries on from where the reader is', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')
        ->andReturnUsing(fn (string $repository, string $branch, int $page): array => changelogPage($page));

    $this->getJson('/changelog?timezone=Europe/Budapest')
        ->assertJsonCount(3, 'commits')
        ->assertJsonPath('commits.2.pull_request.number', 375);

    $this->getJson('/changelog?'.http_build_query(['timezone' => 'UTC', 'before' => '2026-10-15T00:00:00+00:00']))
        ->assertJsonCount(1, 'commits')
        ->assertJsonPath('commits.0.pull_request.number', 374)
        ->assertJsonPath('olderBefore', null);
});

it('rejects a time zone or a starting point it cannot read', function (array $query, string $field): void {
    $this->getJson('/changelog?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown time zone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'not a date' => [['timezone' => 'UTC', 'before' => 'yesterday-ish'], 'before'],
]);

it('says the changelog is unavailable when GitHub cannot be reached', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->andThrow(new ConnectionException('timed out'));

    $this->getJson('/changelog?timezone=UTC')
        ->assertOk()
        ->assertJsonPath('commits', null)
        ->assertJsonPath('olderBefore', null);
});

it('opens the changelog as a page', function (): void {
    config(['services.github.repository' => 'example/upstream']);

    $this->get('/changelog')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('repository', 'example/upstream')
            ->component('changelog/show'));
});

it('describes the changelog to search engines and link previews', function (): void {
    $this->get(route('changelog.index'))
        ->assertOk()
        ->assertSee('<title>Changelog - /dev/null</title>', false)
        ->assertSee('<meta name="robots" content="index, follow', false)
        ->assertSee('<link rel="canonical" href="'.route('changelog.index').'">', false)
        ->assertSee('"@type":"WebPage"', false);
});

it('uses the configured upstream for the changelog and commit links', function (): void {
    config(['services.github.repository' => 'example/upstream']);
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->with('example/upstream', 'production', Mockery::any(), Mockery::any())->andReturn([
        [...changelogControllerCommit(377, '2026-10-17T23:30:00Z', null), 'url' => 'https://github.com/example/upstream/commit/c897f867377'],
    ]);

    $this->getJson('/changelog?timezone=UTC')
        ->assertOk()
        ->assertJsonPath('commitsUrl', 'https://github.com/example/upstream/commits/production')
        ->assertJsonPath('commits.0.url', 'https://github.com/example/upstream/commit/c897f867377')
        ->assertJsonPath('commits.0.pull_request.url', 'https://github.com/example/upstream/pull/377');
});
