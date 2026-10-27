<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\ChangelogQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @param  array{id: int, login: string, type?: string}|null  $author
 * @param  array{id: int, login: string, type?: string}|null  $committer
 * @param  list<string>|null  $parents
 * @return array<string, mixed>
 */
function gitHubCommit(string $sha, string $committedAt, string $message = 'feat: something', ?array $author = ['id' => 14284867, 'login' => 'xHeaven'], ?array $committer = ['id' => 19864447, 'login' => 'web-flow'], ?array $parents = null): array
{
    $account = fn (?array $account): ?array => $account === null ? null : [
        'type' => 'User',
        ...$account,
        'avatar_url' => "https://avatars.githubusercontent.com/u/{$account['id']}?v=4",
    ];

    return [
        'sha' => $sha,
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/commit/{$sha}",
        'commit' => ['message' => $message, 'committer' => ['date' => $committedAt]],
        'author' => $account($author),
        'committer' => $account($committer),
        'parents' => array_map(fn (string $parent): array => ['sha' => $parent], $parents ?? []),
    ];
}

/**
 * A line of commits, newest first, each the first parent of the one before it.
 *
 * @param  list<array<string, mixed>>  $commits
 * @return list<array<string, mixed>>
 */
function commitLine(array $commits): array
{
    return array_map(fn (array $commit, int $index): array => [
        ...$commit,
        'parents' => $commit['parents'] === [] && isset($commits[$index + 1]) ? [['sha' => $commits[$index + 1]['sha']]] : $commit['parents'],
    ], $commits, array_keys($commits));
}

/**
 * Serves production's history like GitHub does: a hundred commits at a time, newest first, from the given commit.
 *
 * @param  list<array<string, mixed>>  $commits
 */
function fakeCommits(array $commits): void
{
    Http::fake(['api.github.com/repos/dev-null-sh/dev-null.sh/commits*' => function (Request $request) use ($commits) {
        $from = $request['sha'] === 'production' ? 0 : (int) array_search($request['sha'], array_column($commits, 'sha'), true);

        return Http::response(array_slice($commits, $from, 100));
    }]);
}

/**
 * @param  array{commits: list<array{sha: string}>, olderBefore: string|null}|null  $changelog
 * @return list<string>
 */
function shas(?array $changelog): array
{
    return array_column($changelog['commits'] ?? [], 'sha');
}

it('lists the commits on the three latest days with commits, newest first, with their parsed titles and pull requests', function (): void {
    fakeCommits(commitLine([
        gitHubCommit('f', '2026-10-18T00:53:44Z', "feat!: turn emoji shortcodes into emoji in remotes (#377)\n\nThe body."),
        gitHubCommit('e', '2026-10-17T21:26:43Z', 'chore: deps', ['id' => 101, 'login' => 'nunomaduro'], ['id' => 101, 'login' => 'nunomaduro']),
        gitHubCommit('d', '2026-10-17T20:15:34Z', 'Fix GitHub website URLs with uppercase schemes (#304)', ['id' => 102, 'login' => 'ahosker']),
        gitHubCommit('c', '2026-10-14T10:00:00Z', "Merge pull request #250 from simonhamp/feat/hover\n\nfeat: dim other commits on mouse hover", ['id' => 103, 'login' => 'simonhamp'], parents: ['b', 'z']),
        gitHubCommit('b', '2026-10-12T10:00:00Z'),
        gitHubCommit('a', '2026-10-11T10:00:00Z'),
    ]));

    $changelog = resolve(ChangelogQuery::class)->get('UTC');

    expect(shas($changelog))->toBe(['f', 'e', 'd', 'c'])
        ->and($changelog['olderBefore'] ?? null)->toBe('2026-10-14T00:00:00+00:00')
        ->and($changelog['commits'][0] ?? null)->toBe([
            'sha' => 'f',
            'type' => 'feat',
            'breaking' => true,
            'title' => 'Turn emoji shortcodes into emoji in remotes',
            'url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/f',
            'pull_request' => ['number' => 377, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/377'],
            'committed_at' => '2026-10-18T00:53:44Z',
            'author' => ['login' => 'xHeaven', 'avatar' => 'https://avatars.githubusercontent.com/u/14284867?v=4', 'handle' => null, 'mention' => null, 'lane' => null],
        ])
        ->and($changelog['commits'][1] ?? [])->toMatchArray(['type' => 'chore', 'title' => 'Deps', 'pull_request' => null])
        ->and($changelog['commits'][2] ?? [])->toMatchArray(['type' => null, 'title' => 'Fix GitHub website URLs with uppercase schemes'])
        ->and($changelog['commits'][3] ?? [])->toMatchArray([
            'type' => 'feat',
            'title' => 'Dim other commits on mouse hover',
            'pull_request' => ['number' => 250, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/250'],
        ]);

    Http::assertSent(fn (Request $request): bool => $request['sha'] === 'production' && $request['per_page'] === 100);
});

it('follows production’s own line, leaving out the commits of merged branches and the merges of branches', function (): void {
    fakeCommits([
        gitHubCommit('merge-pr', '2026-10-18T12:00:00Z', "Merge pull request #655 from nunomaduro/feat/funnel\n\nfeat: metrics funnel", parents: ['direct', 'branch-2']),
        gitHubCommit('branch-2', '2026-10-18T11:00:00Z', 'wip', parents: ['branch-1']),
        gitHubCommit('branch-1', '2026-10-18T10:00:00Z', 'wip', parents: ['direct']),
        gitHubCommit('direct', '2026-10-18T09:00:00Z', 'feat: pings', parents: ['merge-branch']),
        gitHubCommit('merge-branch', '2026-10-18T08:00:00Z', "Merge branch 'feat/macos-app' into production", parents: ['root', 'macos']),
        gitHubCommit('macos', '2026-10-18T07:00:00Z', 'feat: new mac os build', parents: ['root']),
        gitHubCommit('root', '2026-10-18T06:00:00Z', 'feat: we back baby'),
    ]);

    expect(shas(resolve(ChangelogQuery::class)->get('UTC')))->toBe(['merge-pr', 'direct', 'root']);
});

it('shows a commit an agent or a bot made under the person who committed it, and never the agent or bot', function (): void {
    fakeCommits(commitLine([
        gitHubCommit('amp', '2026-10-18T12:00:00Z', author: ['id' => 1, 'login' => 'ampagent'], committer: ['id' => 2, 'login' => 'paulinevos']),
        gitHubCommit('claude', '2026-10-18T11:00:00Z', author: ['id' => 3, 'login' => 'Claude'], committer: ['id' => 19864447, 'login' => 'web-flow']),
        gitHubCommit('bot', '2026-10-18T10:00:00Z', author: ['id' => 4, 'login' => 'dependabot[bot]', 'type' => 'Bot'], committer: ['id' => 5, 'login' => 'nunomaduro']),
        gitHubCommit('unknown', '2026-10-18T09:00:00Z', author: null, committer: ['id' => 19864447, 'login' => 'web-flow']),
    ]));

    $changelog = resolve(ChangelogQuery::class)->get('UTC');

    expect(shas($changelog))->toBe(['amp', 'bot'])
        ->and(array_column(array_column($changelog['commits'] ?? [], 'author'), 'login'))->toBe(['paulinevos', 'nunomaduro']);
});

it('counts days in the reader’s time zone', function (): void {
    fakeCommits(commitLine([
        gitHubCommit('d', '2026-10-17T23:30:00Z'),
        gitHubCommit('c', '2026-10-17T12:00:00Z'),
        gitHubCommit('b', '2026-10-16T12:00:00Z'),
        gitHubCommit('a', '2026-10-15T12:00:00Z'),
    ]));

    $query = resolve(ChangelogQuery::class);

    expect(shas($query->get('UTC')))->toBe(['d', 'c', 'b', 'a'])
        ->and(shas($query->get('Europe/Budapest')))->toBe(['d', 'c', 'b'])
        ->and($query->get('Europe/Budapest')['olderBefore'] ?? null)->toBe('2026-10-15T22:00:00+00:00');
});

it('reads the line a hundred commits at a time from where it got to, as many pages as the three days take', function (): void {
    fakeCommits(commitLine([
        ...array_map(fn (int $number): array => gitHubCommit("busy-{$number}", CarbonImmutable::parse('2026-10-18T23:59:00Z')->subMinutes($number * 9)->toIso8601ZuluString()), range(1, 300)),
        gitHubCommit('older', '2026-10-12T10:00:00Z'),
        ...array_map(fn (int $number): array => gitHubCommit("old-{$number}", '2026-10-01T10:00:00Z'), range(1, 150)),
    ]));

    $changelog = resolve(ChangelogQuery::class)->get('UTC');

    expect($changelog['commits'] ?? [])->toHaveCount(301)
        ->and($changelog['olderBefore'] ?? null)->toBe('2026-10-12T00:00:00+00:00');

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request['sha'] === 'busy-101');
});

it('lists the next three days older than where the reader is, reusing the pages it already read', function (): void {
    fakeCommits(commitLine([
        gitHubCommit('f', '2026-10-18T10:00:00Z'),
        gitHubCommit('e', '2026-10-17T10:00:00Z'),
        gitHubCommit('d', '2026-10-16T10:00:00Z'),
        gitHubCommit('c', '2026-10-15T10:00:00Z'),
        gitHubCommit('b', '2026-10-13T10:00:00Z'),
        gitHubCommit('a', '2026-10-13T09:00:00Z'),
    ]));

    $query = resolve(ChangelogQuery::class);
    $latest = $query->get('UTC');
    $older = $query->get('UTC', CarbonImmutable::parse((string) $latest['olderBefore']));

    expect(shas($latest))->toBe(['f', 'e', 'd'])
        ->and(shas($older))->toBe(['c', 'b', 'a'])
        ->and($older)->toHaveKey('olderBefore', null);

    Http::assertSentCount(1);
});

it('stops reading after twenty pages, carrying on from the oldest commit it got to next time', function (): void {
    fakeCommits(commitLine(array_map(fn (int $number): array => gitHubCommit("c-{$number}", CarbonImmutable::parse('2026-10-18T23:59:00Z')->subSeconds($number * 30)->toIso8601ZuluString()), range(1, 2100))));

    $changelog = resolve(ChangelogQuery::class)->get('UTC');

    expect($changelog['commits'] ?? [])->toHaveCount(2000)
        ->and($changelog['olderBefore'] ?? null)->toBe(CarbonImmutable::parse('2026-10-18T23:59:00Z')->subSeconds(2000 * 30)->toIso8601String());

    Http::assertSentCount(20);
});

it('colors each author with the lane they chose on /dev/null, found by their GitHub account', function (): void {
    User::factory()->create(['github_id' => '1', 'preferred_lane' => 4]);
    User::factory()->create(['github_id' => '2', 'lane_color' => '#ff2d20']);

    fakeCommits(commitLine([
        gitHubCommit('c', '2026-10-18T12:00:00Z', author: ['id' => 1, 'login' => 'renamed-on-github']),
        gitHubCommit('b', '2026-10-18T11:00:00Z', author: ['id' => 2, 'login' => 'custom']),
        gitHubCommit('a', '2026-10-18T10:00:00Z', author: ['id' => 3, 'login' => 'not-on-devnull']),
    ]));

    expect(array_column(array_column(resolve(ChangelogQuery::class)->get('UTC')['commits'] ?? [], 'author'), 'lane'))
        ->toBe([4, '#ff2d20', null]);
});

it('links each author who is on /dev/null to their profile, found by their GitHub account', function (): void {
    User::factory()->create(['github_id' => '1', 'handle' => 'renamed-on-devnull']);

    fakeCommits(commitLine([
        gitHubCommit('b', '2026-10-18T12:00:00Z', author: ['id' => 1, 'login' => 'renamed-on-github']),
        gitHubCommit('a', '2026-10-18T10:00:00Z', author: ['id' => 3, 'login' => 'not-on-devnull']),
    ]));

    $authors = array_column(resolve(ChangelogQuery::class)->get('UTC')['commits'] ?? [], 'author');

    expect(array_column($authors, 'handle'))->toBe(['renamed-on-devnull', null])
        ->and(array_column($authors, 'mention'))->toBe(['@renamed-on-devnull', null]);
});

it('asks GitHub for a page at most once every thirty minutes', function (): void {
    fakeCommits([gitHubCommit('a', '2026-10-18T00:53:44Z')]);

    $query = resolve(ChangelogQuery::class);
    $query->get('UTC');
    $query->get('Europe/Budapest');

    Http::assertSentCount(1);

    $this->travel(29)->minutes();
    $query->get('UTC');

    Http::assertSentCount(1);

    $this->travel(2)->minutes();
    $query->get('UTC');

    Http::assertSentCount(2);
});

it('is null when GitHub cannot be reached, and asks again next time', function (mixed $response): void {
    Http::fake(['api.github.com/*' => Http::sequence()->pushResponse($response)->push([gitHubCommit('a', '2026-10-18T00:53:44Z')])]);

    $query = resolve(ChangelogQuery::class);

    expect($query->get('UTC'))->toBeNull()
        ->and(shas($query->get('UTC')))->toBe(['a']);
})->with([
    'an error' => fn () => Http::response(['message' => 'API rate limit exceeded'], 403),
    'a timeout' => fn () => Http::failedConnection(),
    'something unexpected' => fn () => Http::response(['message' => 'Not Found']),
]);
