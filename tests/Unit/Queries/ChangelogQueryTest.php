<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use App\Queries\ChangelogQuery;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

function changelogSha(int $number): string
{
    return mb_str_pad((string) $number, 40, '0', STR_PAD_LEFT);
}

/**
 * @param  list<string>  $parents
 * @return array{sha: string, message: string, url: string, committed_at: string, parents: list<string>, author: array{id: int|null, login: string, avatar_url: string}}
 */
function changelogCommit(int $number, string $committedAt, ?string $message = null, string $login = 'xHeaven', ?int $githubId = 14284867, array $parents = []): array
{
    return [
        'sha' => changelogSha($number),
        'message' => $message ?? "feat: something (#{$number})",
        'url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/'.changelogSha($number),
        'committed_at' => $committedAt,
        'parents' => $parents,
        'author' => ['id' => $githubId, 'login' => $login, 'avatar_url' => "https://avatars.githubusercontent.com/u/{$number}?v=4"],
    ];
}

/**
 * Links each commit, newest first, to the one after it as its first parent, like a branch that only moves forward.
 *
 * @param  list<array<string, mixed>>  $commits
 * @return list<array<string, mixed>>
 */
function changelogLine(array $commits): array
{
    return array_map(fn (array $commit, ?array $parent): array => [
        ...$commit,
        'parents' => [...($parent === null ? [] : [$parent['sha']]), ...$commit['parents']],
    ], $commits, [...array_slice($commits, 1), null]);
}

/**
 * Serves the commits like GitHub does: most recently committed first, a page at a time.
 *
 * @param  list<array<string, mixed>>  $commits
 * @return Closure(string, string, int, int): list<array<string, mixed>>
 */
function changelogPages(array $commits): Closure
{
    usort($commits, fn (array $a, array $b): int => strcmp((string) $b['committed_at'], (string) $a['committed_at']));

    return fn (string $repository, string $branch, int $page, int $perPage): array => array_slice($commits, ($page - 1) * $perPage, $perPage);
}

/**
 * @param  array{commits: list<array{sha: string}>, olderBefore: string|null}|null  $changelog
 * @return list<int>
 */
function changelogNumbers(?array $changelog): array
{
    return array_map(intval(...), array_column($changelog['commits'] ?? [], 'sha'));
}

it('lists the commits on the first-parent line of the three latest days with commits, newest first, with their parsed titles', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages([
        ...changelogLine([
            changelogCommit(377, '2026-10-18T00:53:44Z', 'feat!: turn emoji shortcodes into emoji in remotes (#377)'),
            changelogCommit(376, '2026-10-18T00:40:00Z', 'fix: pushed straight to production', login: 'nunomaduro'),
            changelogCommit(375, '2026-10-17T23:00:00Z', "Merge branch 'feat/macos-app' into production", parents: [changelogSha(900)]),
            changelogCommit(310, '2026-10-17T21:26:43Z', "Merge pull request #310 from simonhamp/hover\n\nfeat: dim other commits on mouse hover", login: 'simonhamp', parents: [changelogSha(901)]),
            changelogCommit(305, '2026-10-17T20:30:00Z', "Merge remote-tracking branch 'origin/production'\n\nRevert the hover", parents: [changelogSha(902)]),
            changelogCommit(304, '2026-10-17T20:15:34Z', 'Fix GitHub website URLs with uppercase schemes', login: 'ahosker'),
            changelogCommit(250, '2026-10-14T10:00:00Z'),
            changelogCommit(240, '2026-10-12T10:00:00Z'),
        ]),
        changelogCommit(900, '2026-10-17T22:00:00Z', 'feat: a commit of a merged branch'),
    ]));

    $changelog = resolve(ChangelogQuery::class)->get('UTC');

    expect(changelogNumbers($changelog))->toBe([377, 376, 310, 305, 304, 250])
        ->and($changelog['olderBefore'] ?? null)->toBe('2026-10-14T00:00:00+00:00')
        ->and($changelog['commits'][0] ?? null)->toBe([
            'sha' => changelogSha(377),
            'url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/'.changelogSha(377),
            'pull_request' => ['number' => 377, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/377'],
            'type' => 'feat',
            'breaking' => true,
            'title' => 'Turn emoji shortcodes into emoji in remotes',
            'committed_at' => '2026-10-18T00:53:44Z',
            'author' => ['login' => 'xHeaven', 'avatar' => 'https://avatars.githubusercontent.com/u/377?v=4', 'handle' => null, 'mention' => null, 'lane' => null],
        ])
        ->and(array_column($changelog['commits'] ?? [], 'pull_request'))->toBe([
            ['number' => 377, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/377'],
            null,
            ['number' => 310, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/310'],
            null,
            null,
            ['number' => 250, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/250'],
        ])
        ->and(array_column($changelog['commits'] ?? [], 'title'))->toBe([
            'Turn emoji shortcodes into emoji in remotes',
            'Pushed straight to production',
            'Dim other commits on mouse hover',
            'Revert the hover',
            'Fix GitHub website URLs with uppercase schemes',
            'Something',
        ])
        ->and($changelog['commits'][4] ?? [])->toHaveKey('type', null);

    $github->shouldHaveReceived('commits')->with('dev-null-sh/dev-null.sh', 'production', 1, 100)->once();
});

it('counts days in the reader’s time zone', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages(changelogLine([
        changelogCommit(3, '2026-10-17T23:30:00Z'),
        changelogCommit(2, '2026-10-17T12:00:00Z'),
        changelogCommit(1, '2026-10-16T12:00:00Z'),
        changelogCommit(0, '2026-10-15T12:00:00Z'),
    ])));

    $query = resolve(ChangelogQuery::class);

    expect(changelogNumbers($query->get('UTC')))->toBe([3, 2, 1, 0])
        ->and(changelogNumbers($query->get('Europe/Budapest')))->toBe([3, 2, 1])
        ->and($query->get('Europe/Budapest')['olderBefore'] ?? null)->toBe('2026-10-15T22:00:00+00:00');
});

it('reads as many pages as the three days take, and no more', function (): void {
    $busyDays = array_map(fn (int $number): array => changelogCommit($number, CarbonImmutable::parse('2026-10-18T23:59:00Z')->subMinutes($number * 9)->toIso8601ZuluString()), range(1, 300));

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages(changelogLine([...$busyDays, changelogCommit(2000, '2026-10-12T10:00:00Z'), ...array_map(fn (int $number): array => changelogCommit(1000 + $number, '2026-10-01T10:00:00Z'), range(1, 150))])));

    $changelog = resolve(ChangelogQuery::class)->get('UTC');

    expect($changelog['commits'] ?? [])->toHaveCount(301)
        ->and($changelog['olderBefore'] ?? null)->toBe('2026-10-12T00:00:00+00:00');

    $github->shouldHaveReceived('commits')->times(4);
});

it('lists the next three days older than where the reader is, reusing the pages it already read', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages(changelogLine([
        changelogCommit(6, '2026-10-18T10:00:00Z'),
        changelogCommit(5, '2026-10-17T10:00:00Z'),
        changelogCommit(4, '2026-10-16T10:00:00Z'),
        changelogCommit(3, '2026-10-15T10:00:00Z'),
        changelogCommit(2, '2026-10-13T10:00:00Z'),
        changelogCommit(1, '2026-10-13T09:00:00Z'),
    ])));

    $query = resolve(ChangelogQuery::class);
    $latest = $query->get('UTC');
    $older = $query->get('UTC', CarbonImmutable::parse((string) $latest['olderBefore']));

    expect(changelogNumbers($latest))->toBe([6, 5, 4])
        ->and(changelogNumbers($older))->toBe([3, 2, 1])
        ->and($older)->toHaveKey('olderBefore', null);

    $github->shouldHaveReceived('commits')->times(1);
});

it('stops reading after ten pages, carrying on from the oldest commit it got to next time', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages(changelogLine(array_map(fn (int $number): array => changelogCommit($number, CarbonImmutable::parse('2026-10-18T23:59:00Z')->subSeconds($number * 30)->toIso8601ZuluString()), range(1, 1100)))));

    $changelog = resolve(ChangelogQuery::class)->get('UTC');

    expect($changelog['commits'] ?? [])->toHaveCount(1000)
        ->and($changelog['olderBefore'] ?? null)->toBe(CarbonImmutable::parse('2026-10-18T23:59:00Z')->subSeconds(1000 * 30)->toIso8601String());

    $github->shouldHaveReceived('commits')->times(10);
});

it('colors each author with the lane they chose on /dev/null, found by their GitHub account', function (): void {
    User::factory()->create(['github_id' => '1', 'preferred_lane' => 4]);
    User::factory()->create(['github_id' => '2', 'lane_color' => '#ff2d20']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages(changelogLine([
        changelogCommit(3, '2026-10-18T12:00:00Z', login: 'renamed-on-github', githubId: 1),
        changelogCommit(2, '2026-10-18T11:00:00Z', login: 'custom', githubId: 2),
        changelogCommit(1, '2026-10-18T10:00:00Z', login: 'not-on-devnull', githubId: 3),
    ])));

    expect(array_column(array_column(resolve(ChangelogQuery::class)->get('UTC')['commits'] ?? [], 'author'), 'lane'))
        ->toBe([4, '#ff2d20', null]);
});

it('links each author who is on /dev/null to their profile, found by their GitHub account', function (): void {
    User::factory()->create(['github_id' => '1', 'handle' => 'renamed-on-devnull']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages(changelogLine([
        changelogCommit(3, '2026-10-18T12:00:00Z', login: 'renamed-on-github', githubId: 1),
        changelogCommit(2, '2026-10-18T11:00:00Z', login: 'not-on-devnull', githubId: 3),
        changelogCommit(1, '2026-10-18T10:00:00Z', login: 'Someone Without An Account', githubId: null),
    ])));

    $authors = array_column(resolve(ChangelogQuery::class)->get('UTC')['commits'] ?? [], 'author');

    expect(array_column($authors, 'handle'))->toBe(['renamed-on-devnull', null, null])
        ->and(array_column($authors, 'mention'))->toBe(['@renamed-on-devnull', null, null])
        ->and(array_column($authors, 'login'))->toBe(['renamed-on-github', 'not-on-devnull', 'Someone Without An Account']);
});

it('asks GitHub for a page at most once every thirty minutes', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing(changelogPages([changelogCommit(377, '2026-10-18T00:53:44Z')]));

    $query = resolve(ChangelogQuery::class);
    $query->get('UTC');
    $query->get('Europe/Budapest');

    $github->shouldHaveReceived('commits')->times(1);

    $this->travel(29)->minutes();
    $query->get('UTC');

    $github->shouldHaveReceived('commits')->times(1);

    $this->travel(2)->minutes();
    $query->get('UTC');

    $github->shouldHaveReceived('commits')->times(2);
});

it('is null when GitHub cannot be reached, and asks again next time', function (Closure $exception): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->andReturnUsing($exception, fn (): array => [changelogCommit(377, '2026-10-18T00:53:44Z')]);

    $query = resolve(ChangelogQuery::class);

    expect($query->get('UTC'))->toBeNull()
        ->and(changelogNumbers($query->get('UTC')))->toBe([377]);
})->with([
    'an error' => fn () => throw new RequestException(new Response(new Psr7Response(403))),
    'a timeout' => fn () => throw new ConnectionException('timed out'),
]);
