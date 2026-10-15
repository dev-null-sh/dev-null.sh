<?php

declare(strict_types=1);

use App\Services\GitHubRepoPet;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('offers only active workflows from a public repository with public app credentials', function (): void {
    config(['services.github.client_id' => 'app-id', 'services.github.client_secret' => 'app-secret']);
    Http::fake([
        'api.github.com/repos/owner/repo' => Http::response(repoPetRepository()),
        'api.github.com/repos/Owner/Repo/actions/workflows?per_page=100' => Http::response(['workflows' => [
            ['id' => 123, 'name' => 'Tests', 'state' => 'active'],
            ['id' => 456, 'name' => 'Disabled', 'state' => 'disabled_manually'],
        ]]),
    ]);

    expect(resolve(GitHubRepoPet::class)->options('owner/repo'))->toBe([
        'repository' => 'Owner/Repo', 'repository_id' => 7, 'workflows' => [['id' => 123, 'name' => 'Tests']],
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/Owner/Repo/actions/workflows?per_page=100'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('app-id:app-secret')));
});

it('uses no authorization header without complete nonempty app credentials', function (?string $id, ?string $secret): void {
    config(['services.github.client_id' => $id, 'services.github.client_secret' => $secret]);
    Http::fake([
        'api.github.com/repos/Owner/Repo' => Http::response(repoPetRepository()),
        'api.github.com/repos/Owner/Repo/actions/workflows*' => Http::response(['workflows' => []]),
    ]);

    expect(resolve(GitHubRepoPet::class)->options('Owner/Repo')['workflows'])->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization'));
})->with([[null, null], ['', ''], ['app-id', ''], ['', 'app-secret']]);

it('rejects repository paths outside an owner and repository pair before making requests', function (string $repository): void {
    expect(resolve(GitHubRepoPet::class)->options($repository))->toBeNull();

    Http::assertNothingSent();
})->with(['https://github.com/owner/repo', 'owner/../secret', 'owner/..', 'owner/.', 'owner/repo?x=1', 'owner/repo#main', 'owner/repo/extra']);

it('requires public and well formed repository metadata', function (array $changes): void {
    Http::fake(['api.github.com/repos/Owner/Repo' => Http::response([...repoPetRepository(), ...$changes])]);

    expect(resolve(GitHubRepoPet::class)->options('Owner/Repo'))->toBeNull();

    Http::assertSentCount(1);
})->with([
    'private' => [['private' => true]],
    'visibility mismatch' => [['visibility' => 'private']],
    'missing visibility proof' => [['private' => null]],
    'invalid id' => [['id' => '7']],
    'invalid name' => [['full_name' => 'elsewhere.test/repo/path']],
    'missing branch' => [['default_branch' => null]],
]);

it('returns unavailable options for github failures and redirects', function (int $status): void {
    Http::fake(['api.github.com/repos/Owner/Repo' => Http::response(['message' => 'Unavailable'], $status, ['Location' => 'https://elsewhere.test/repo'])]);

    expect(resolve(GitHubRepoPet::class)->options('Owner/Repo'))->toBeNull();

    Http::assertSentCount(1);
})->with([301, 403, 404, 429, 500]);

it('returns unavailable options when github cannot be reached', function (): void {
    Http::fake(['api.github.com/repos/Owner/Repo' => Http::failedConnection()]);

    expect(resolve(GitHubRepoPet::class)->options('Owner/Repo'))->toBeNull();

    Http::assertSentCount(1);
});

it('rejects malformed workflow choices', function (array $response): void {
    Http::fake([
        'api.github.com/repos/Owner/Repo' => Http::response(repoPetRepository()),
        'api.github.com/repos/Owner/Repo/actions/workflows*' => Http::response($response),
    ]);

    expect(resolve(GitHubRepoPet::class)->options('Owner/Repo'))->toBeNull();
})->with([
    'missing list' => [[]],
    'object instead of list' => [['workflows' => ['id' => 123]]],
    'malformed workflow' => [['workflows' => [['id' => '123', 'state' => 'active', 'name' => 'Tests']]]],
]);

it('offers no more than one hundred workflow choices', function (): void {
    Http::fake([
        'api.github.com/repos/Owner/Repo' => Http::response(repoPetRepository()),
        'api.github.com/repos/Owner/Repo/actions/workflows?per_page=100' => Http::response(['workflows' => array_map(
            fn (int $id): array => ['id' => $id, 'name' => 'Workflow '.$id, 'state' => 'active'], range(1, 100),
        )]),
    ]);

    expect(resolve(GitHubRepoPet::class)->options('Owner/Repo')['workflows'])->toHaveCount(100);
    Http::assertSentCount(2);
});

it('maps the latest selected workflow run at the default branch head to a mood', function (string $status, ?string $conclusion, string $mood): void {
    fakeRepoPetSnapshot([repoPetRun(['status' => $status, 'conclusion' => $conclusion])]);

    $result = resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration());

    expect($result)->toMatchArray([
        'mood' => $mood, 'url' => 'https://github.com/Owner/Repo/actions/runs/900',
        'checked_at' => now()->toIso8601String(),
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/Owner/Repo/actions/workflows/123/runs?branch=main&head_sha='.str_repeat('a', 40).'&per_page=100');
    Http::assertSentCount(6);
})->with([
    ['completed', 'success', 'happy'], ['completed', 'failure', 'grumpy'], ['completed', 'timed_out', 'grumpy'],
    ['queued', null, 'busy'], ['in_progress', null, 'busy'], ['waiting', null, 'busy'],
    ['pending', null, 'busy'], ['requested', null, 'busy'], ['completed', 'cancelled', 'sleeping'],
    ['completed', 'skipped', 'sleeping'], ['completed', 'neutral', 'sleeping'], ['completed', 'stale', 'sleeping'],
    ['completed', 'unknown', 'sleeping'], ['completed', null, 'sleeping'], ['unknown', null, 'sleeping'],
]);

it('accepts non-pull-request events at the current default branch head', function (string $event): void {
    fakeRepoPetSnapshot([repoPetRun(['event' => $event])]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('happy');
})->with(['push', 'workflow_dispatch', 'schedule', 'workflow_run', 'repository_dispatch', 'issues']);

it('does not use other workflows old heads feature branches or pull request builds', function (array $changes): void {
    fakeRepoPetSnapshot([repoPetRun($changes)]);

    $result = resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration());

    expect($result['mood'])->toBe('sleeping')
        ->and($result['url'])->toBe('https://github.com/Owner/Repo/actions');
})->with([
    'other workflow' => [['workflow_id' => 456]],
    'old head' => [['head_sha' => str_repeat('b', 40)]],
    'other branch' => [['head_branch' => 'feature']],
    'pull request' => [['event' => 'pull_request']],
    'pull request target' => [['event' => 'pull_request_target']],
    'pull request review' => [['event' => 'pull_request_review']],
    'missing event' => [['event' => null]],
    'empty event' => [['event' => '']],
    'other repository' => [['repository' => ['id' => 99, 'private' => false]]],
    'fork head repository' => [['head_repository' => ['id' => 99, 'private' => false]]],
    'private repository' => [['repository' => ['id' => 7, 'private' => true]]],
    'missing head identity' => [['head_repository' => null]],
    'invalid run id' => [['id' => '900/../../other']],
]);

it('chooses the newest eligible run and does not fall back from an unknown conclusion', function (): void {
    fakeRepoPetSnapshot([
        repoPetRun(['id' => 899, 'conclusion' => 'success']),
        repoPetRun(['id' => 901, 'conclusion' => 'cancelled']),
        repoPetRun(['id' => 900, 'conclusion' => 'failure']),
    ]);

    $result = resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration());

    expect($result['mood'])->toBe('sleeping')
        ->and($result['url'])->toBe('https://github.com/Owner/Repo/actions/runs/901');
});

it('constructs the run link from trusted repository and numeric run id', function (): void {
    fakeRepoPetSnapshot([repoPetRun(['html_url' => 'javascript:alert(1)'])]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['url'])->toBe('https://github.com/Owner/Repo/actions/runs/900');
});

it('sleeps when no run exists at the current default branch head', function (): void {
    fakeRepoPetSnapshot([]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
});

it('sleeps when the selected workflow has been removed', function (): void {
    Http::fake(['api.github.com/repos/Owner/Repo/actions/workflows/123' => Http::response(['message' => 'Not Found'], 404)]);
    fakeRepoPetSnapshot([repoPetRun()]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
    Http::assertSentCount(2);
});

it('sleeps when the current branch response is malformed', function (): void {
    Http::fake(['api.github.com/repos/Owner/Repo/branches/main' => Http::response(['name' => 'main', 'commit' => ['sha' => 'broken']])]);
    fakeRepoPetSnapshot([repoPetRun()]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
});

it('sleeps when github returns malformed workflow runs', function (): void {
    Http::fake(['api.github.com/repos/Owner/Repo/actions/workflows/123/runs*' => Http::response(['workflow_runs' => ['id' => 900]])]);
    fakeRepoPetSnapshot([repoPetRun()]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
});

it('does not accept more than the requested one hundred runs', function (): void {
    fakeRepoPetSnapshot(array_fill(0, 101, repoPetRun()));

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
});

it('sleeps when the repository was replaced or became private', function (array $changes): void {
    fakeRepoPetSnapshot([repoPetRun()], $changes);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
    Http::assertSentCount(1);
})->with([[['id' => 99]], [['private' => true]]]);

it('sleeps when the selected workflow is missing inactive or mismatched', function (array $workflow): void {
    fakeRepoPetSnapshot([repoPetRun()], workflow: $workflow);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
    Http::assertSentCount(2);
})->with([[['state' => 'disabled_manually']], [['id' => 456]]]);

it('sleeps if a push changes the head while the build status is read', function (string $status, ?string $conclusion): void {
    fakeRepoPetSnapshot([repoPetRun(['status' => $status, 'conclusion' => $conclusion])], finalHead: str_repeat('b', 40));

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
})->with([['completed', 'success'], ['completed', 'failure'], ['in_progress', null]]);

it('sleeps if the default branch or repository visibility changes while reading status', function (array $changes): void {
    fakeRepoPetSnapshot([repoPetRun()], finalRepository: $changes);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
})->with([[['default_branch' => 'develop']], [['private' => true]], [['id' => 99]]]);

it('supports default branch names containing slashes', function (): void {
    fakeRepoPetSnapshot([repoPetRun(['head_branch' => 'release/stable'])], ['default_branch' => 'release/stable']);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('happy');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/Owner/Repo/branches/release%2Fstable');
});

it('sleeps when github fails instead of retaining a successful status', function (int $status): void {
    Http::fake(['api.github.com/repos/Owner/Repo' => Http::response(['message' => 'Unavailable'], $status)]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
})->with([301, 403, 404, 429, 500]);

it('sleeps when github times out', function (): void {
    Http::fake(['api.github.com/repos/Owner/Repo' => Http::failedConnection()]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
});

it('fails closed on malformed json', function (): void {
    Http::fake(['api.github.com/repos/Owner/Repo' => Http::response('null')]);

    expect(resolve(GitHubRepoPet::class)->snapshot(repoPetConfiguration())['mood'])->toBe('sleeping');
});

/**
 * @return array{repository: string, repository_id: int, workflow_id: int, workflow: string}
 */
function repoPetConfiguration(): array
{
    return ['repository' => 'Owner/Repo', 'repository_id' => 7, 'workflow_id' => 123, 'workflow' => 'Tests'];
}

/**
 * @return array{id: int, full_name: string, private: bool, visibility: string, default_branch: string}
 */
function repoPetRepository(): array
{
    return ['id' => 7, 'full_name' => 'Owner/Repo', 'private' => false, 'visibility' => 'public', 'default_branch' => 'main'];
}

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function repoPetRun(array $changes = []): array
{
    return [
        'id' => 900, 'workflow_id' => 123, 'head_sha' => str_repeat('a', 40), 'head_branch' => 'main',
        'event' => 'push', 'status' => 'completed', 'conclusion' => 'success',
        'repository' => ['id' => 7, 'private' => false], 'head_repository' => ['id' => 7, 'private' => false],
        ...$changes,
    ];
}

/**
 * @param  list<array<string, mixed>>  $runs
 * @param  array<string, mixed>  $repository
 * @param  array<string, mixed>  $workflow
 * @param  array<string, mixed>  $finalRepository
 */
function fakeRepoPetSnapshot(array $runs, array $repository = [], array $workflow = [], array $finalRepository = [], ?string $finalHead = null): void
{
    $repo = [...repoPetRepository(), ...$repository];
    $branch = is_string($repo['default_branch']) ? $repo['default_branch'] : 'main';

    Http::fake([
        'api.github.com/repos/Owner/Repo' => Http::sequence()->push($repo)->push([...$repo, ...$finalRepository]),
        'api.github.com/repos/Owner/Repo/actions/workflows/123' => Http::response(['id' => 123, 'state' => 'active', ...$workflow]),
        'api.github.com/repos/Owner/Repo/branches/'.rawurlencode($branch) => Http::sequence()
            ->push(['name' => $branch, 'commit' => ['sha' => str_repeat('a', 40)]])
            ->push(['name' => $branch, 'commit' => ['sha' => $finalHead ?? str_repeat('a', 40)]]),
        'api.github.com/repos/Owner/Repo/actions/workflows/123/runs*' => Http::response(['workflow_runs' => $runs]),
    ]);
}
