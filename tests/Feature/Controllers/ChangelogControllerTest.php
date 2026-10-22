<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/**
 * @return array<string, mixed>
 */
function changelogPullRequest(int $number, string $mergedAt): array
{
    return [
        'number' => $number,
        'title' => 'feat: turn emoji shortcodes into emoji in remotes',
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/pull/{$number}",
        'merged_at' => $mergedAt,
        'updated_at' => $mergedAt,
        'merge_commit_sha' => 'c897f86700000000000000000000000000000000',
        'user' => ['id' => 14284867, 'login' => 'xHeaven', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4'],
    ];
}

function fakeChangelog(): void
{
    Http::fake(['api.github.com/*' => fn (Request $request) => Http::response($request['page'] === 1 ? [
        changelogPullRequest(377, '2026-10-17T23:30:00Z'),
        changelogPullRequest(376, '2026-10-16T12:00:00Z'),
        changelogPullRequest(375, '2026-10-15T12:00:00Z'),
        changelogPullRequest(374, '2026-10-14T12:00:00Z'),
    ] : [])]);
}

it('lists the latest days of merged pull requests for anyone, with a link to every commit on production', function (bool $isSignedIn): void {
    fakeChangelog();

    $request = $isSignedIn ? $this->actingAs(User::factory()->create()) : $this;

    $request->getJson('/changelog?timezone=UTC')
        ->assertOk()
        ->assertJsonPath('commitsUrl', 'https://github.com/dev-null-sh/dev-null.sh/commits/production')
        ->assertJsonPath('pullRequests.0.number', 377)
        ->assertJsonPath('pullRequests.0.type', 'feat')
        ->assertJsonPath('pullRequests.0.title', 'Turn emoji shortcodes into emoji in remotes')
        ->assertJsonPath('pullRequests.0.author.login', 'xHeaven')
        ->assertJsonCount(3, 'pullRequests')
        ->assertJsonPath('olderBefore', '2026-10-15T00:00:00+00:00');
})->with(['guest' => false, 'signed in' => true]);

it('groups days in the reader’s time zone, and carries on from where the reader is', function (): void {
    fakeChangelog();

    $this->getJson('/changelog?timezone=Europe/Budapest')
        ->assertJsonCount(3, 'pullRequests')
        ->assertJsonPath('pullRequests.2.number', 375);

    $this->getJson('/changelog?'.http_build_query(['timezone' => 'UTC', 'before' => '2026-10-15T00:00:00+00:00']))
        ->assertJsonCount(1, 'pullRequests')
        ->assertJsonPath('pullRequests.0.number', 374)
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
        ->assertJsonPath('pullRequests', null)
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
    Http::fake(['api.github.com/repos/example/upstream/pulls*' => Http::response([
        [...changelogPullRequest(377, '2026-10-17T23:30:00Z'), 'html_url' => 'https://github.com/example/upstream/pull/377'],
    ])]);

    $this->getJson('/changelog?timezone=UTC')
        ->assertOk()
        ->assertJsonPath('commitsUrl', 'https://github.com/example/upstream/commits/production')
        ->assertJsonPath('pullRequests.0.url', 'https://github.com/example/upstream/pull/377')
        ->assertJsonPath('pullRequests.0.commit_url', 'https://github.com/example/upstream/commit/c897f86700000000000000000000000000000000');
});
