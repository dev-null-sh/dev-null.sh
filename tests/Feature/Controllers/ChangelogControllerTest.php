<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/**
 * @return array<string, mixed>
 */
function changelogCommit(int $number, string $committedAt, ?int $parent): array
{
    return [
        'sha' => "sha-{$number}",
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/commit/sha-{$number}",
        'commit' => ['message' => "feat: turn emoji shortcodes into emoji in remotes (#{$number})", 'committer' => ['date' => $committedAt]],
        'author' => ['id' => 14284867, 'login' => 'xHeaven', 'type' => 'User', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4'],
        'committer' => ['id' => 19864447, 'login' => 'web-flow', 'type' => 'User', 'avatar_url' => 'https://avatars.githubusercontent.com/u/19864447?v=4'],
        'parents' => $parent === null ? [] : [['sha' => "sha-{$parent}"]],
    ];
}

function fakeChangelog(): void
{
    Http::fake(['api.github.com/*' => Http::response([
        changelogCommit(377, '2026-10-17T23:30:00Z', 376),
        changelogCommit(376, '2026-10-16T12:00:00Z', 375),
        changelogCommit(375, '2026-10-15T12:00:00Z', 374),
        changelogCommit(374, '2026-10-14T12:00:00Z', null),
    ])]);
}

it('lists the latest days of commits on production for anyone, with a link to all of them on GitHub', function (bool $isSignedIn): void {
    fakeChangelog();

    $request = $isSignedIn ? $this->actingAs(User::factory()->create()) : $this;

    $request->getJson('/changelog?timezone=UTC')
        ->assertOk()
        ->assertJsonPath('commitsUrl', 'https://github.com/dev-null-sh/dev-null.sh/commits/production')
        ->assertJsonPath('commits.0.sha', 'sha-377')
        ->assertJsonPath('commits.0.pull_request.number', 377)
        ->assertJsonPath('commits.0.type', 'feat')
        ->assertJsonPath('commits.0.title', 'Turn emoji shortcodes into emoji in remotes')
        ->assertJsonPath('commits.0.author.login', 'xHeaven')
        ->assertJsonCount(3, 'commits')
        ->assertJsonPath('olderBefore', '2026-10-15T00:00:00+00:00');
})->with(['guest' => false, 'signed in' => true]);

it('groups days in the reader’s time zone, and carries on from where the reader is', function (): void {
    fakeChangelog();

    $this->getJson('/changelog?timezone=Europe/Budapest')
        ->assertJsonCount(3, 'commits')
        ->assertJsonPath('commits.2.sha', 'sha-375');

    $this->getJson('/changelog?'.http_build_query(['timezone' => 'UTC', 'before' => '2026-10-15T00:00:00+00:00']))
        ->assertJsonCount(1, 'commits')
        ->assertJsonPath('commits.0.sha', 'sha-374')
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
    Http::fake(['api.github.com/*' => Http::failedConnection()]);

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

it('uses the configured upstream for the changelog and commit links', function (): void {
    config(['services.github.repository' => 'example/upstream']);
    Http::fake(['api.github.com/repos/example/upstream/commits*' => Http::response([
        [...changelogCommit(377, '2026-10-17T23:30:00Z', null), 'html_url' => 'https://github.com/example/upstream/commit/sha-377'],
    ])]);

    $this->getJson('/changelog?timezone=UTC')
        ->assertOk()
        ->assertJsonPath('commitsUrl', 'https://github.com/example/upstream/commits/production')
        ->assertJsonPath('commits.0.pull_request.url', 'https://github.com/example/upstream/pull/377')
        ->assertJsonPath('commits.0.url', 'https://github.com/example/upstream/commit/sha-377');
});
