<?php

declare(strict_types=1);

use App\Ai\Tools\RepositoryInsights;
use App\Contracts\Services\GitHubProvider;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Tools\Request as ToolRequest;

it('returns a bounded commit ranking without bots or unnecessary contributor data', function (): void {
    $contributors = collect(range(1, 12))->map(fn (int $number): array => [
        'type' => 'User', 'login' => "dev{$number}", 'contributions' => 100 - $number,
        'html_url' => "https://github.com/dev{$number}", 'id' => $number, 'email' => 'not-for-the-model@example.com',
    ])->all();
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->with('dev-null-sh/dev-null.sh', 1, 100)->once()->andReturn([
        ['type' => 'Bot', 'login' => 'robot', 'contributions' => 200], ...$contributors,
    ]);

    $result = json_decode(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'contributors', 'repository' => 'other/private'])), true);

    expect($result['contributors'])->toHaveCount(10);
    expect($result['contributors'][0])->toBe(['username' => 'dev1', 'commits' => 99, 'url' => 'https://github.com/dev1']);
    expect($result['contributors'][9]['username'])->toBe('dev10');
    expect($result)->toMatchArray(['complete' => false, 'repository' => 'dev-null-sh/dev-null.sh']);
    expect($result['coverage'])->toContain('Not an exhaustive');
});

it('separates issues from pull requests and reads repository language bytes', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('repository')->with('dev-null-sh/dev-null.sh')->andReturn(['stars' => 42, 'forks' => 9]);
    $github->shouldReceive('languages')->with('dev-null-sh/dev-null.sh')->andReturn(['PHP' => 300, 'TypeScript' => 100]);
    $github->shouldReceive('searchIssues')->with('repo:dev-null-sh/dev-null.sh is:issue is:open', 1, null)->andReturn(['incomplete' => false, 'total' => 13, 'items' => []]);
    $github->shouldReceive('searchIssues')->with('repo:dev-null-sh/dev-null.sh is:pr is:open', 1, null)->andReturn(['incomplete' => false, 'total' => 4, 'items' => []]);

    $tool = new RepositoryInsights;
    $overview = json_decode($tool->handle(new ToolRequest(['metric' => 'overview'])), true);
    $languages = json_decode($tool->handle(new ToolRequest(['metric' => 'languages'])), true);

    expect($overview)->toMatchArray(['stars' => 42, 'forks' => 9, 'open_issues' => 13, 'open_pull_requests' => 4, 'complete' => true]);
    expect($languages['languages'])->toBe(['PHP' => 300, 'TypeScript' => 100]);
    expect($languages['definition'])->toContain('bytes');
    expect($tool->handle(new ToolRequest(['metric' => 'overview'])))->toContain('budget exhausted');
});

it('finds contribution opportunities using either helpful label and returns only titles and links', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('searchIssues')
        ->with('repo:dev-null-sh/dev-null.sh is:issue is:open label:"good first issue","help wanted"', 10, 'updated')
        ->once()
        ->andReturn(['incomplete' => false, 'total' => 1, 'items' => [
            ['title' => str_repeat('x', 400), 'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/12', 'body' => 'Not sent to the model'],
        ]]);

    $result = json_decode(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'opportunities'])), true);

    expect($result['issues'])->toBe([['title' => str_repeat('x', 300), 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/12']]);
    expect($result['complete'])->toBeFalse();
});

it('returns recent production merges from the changelog without internal author data', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('closedPullRequests')->with('dev-null-sh/dev-null.sh', 'production', 1, 100)->andReturn(collect(range(12, 1))->map(fn (int $number): array => [
        'number' => $number, 'title' => "feat: change {$number}",
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/pull/{$number}",
        'merged_at' => sprintf('2026-10-19T00:00:%02dZ', $number), 'updated_at' => sprintf('2026-10-19T00:00:%02dZ', $number),
        'merge_commit_sha' => 'abc', 'user' => ['id' => 1, 'login' => 'alice', 'avatar_url' => 'https://github.com/alice.png'],
    ])->all());

    $result = json_decode(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'recent_changes'])), true);

    expect($result['pull_requests'])->toHaveCount(10);
    expect($result['pull_requests'][0])->toBe([
        'title' => 'Change 12', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/12', 'author' => 'alice', 'merged_at' => '2026-10-19T00:00:12Z',
    ]);
    expect($result['definition'])->toContain('not a verified deployment time');
});

it('keeps the original freshness timestamp until the repository cache expires', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('languages')->andReturn(['PHP' => 100], ['PHP' => 200]);
    $request = new ToolRequest(['metric' => 'languages']);
    $first = new RepositoryInsights()->handle($request);
    $this->travel(14)->minutes();

    expect(new RepositoryInsights()->handle($request))->toBe($first);

    $this->travel(2)->minutes();
    $fresh = json_decode(new RepositoryInsights()->handle($request), true);
    expect($fresh['languages'])->toBe(['PHP' => 200]);
    expect($fresh['as_of'])->toBe(now()->toIso8601String());
    $github->shouldHaveReceived('languages')->twice();
});

it('does not turn GitHub failures into zero counts or cache the failure', function (Closure $failure): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('languages')->andReturnUsing($failure, fn (): array => ['PHP' => 123]);
    $request = new ToolRequest(['metric' => 'languages']);
    $tool = new RepositoryInsights;

    expect($tool->handle($request))->toContain('temporarily unavailable');
    expect(json_decode($tool->handle($request), true)['languages'])->toBe(['PHP' => 123]);
})->with([
    'rate limit' => fn () => throw new RequestException(new Response(new Psr7Response(403, [], '{"message":"secret error detail"}'))),
    'server error' => fn () => throw new RequestException(new Response(new Psr7Response(503))),
    'timeout' => fn () => throw new ConnectionException('timed out'),
]);

it('reports unavailable changelogs and incomplete searches instead of claiming complete evidence', function (string $metric): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('repository')->andReturn(['stars' => 42, 'forks' => 9]);
    $github->shouldReceive('closedPullRequests')->andThrow(new RequestException(new Response(new Psr7Response(503))));
    $github->shouldReceive('searchIssues')->andReturn(['incomplete' => true, 'total' => 0, 'items' => []]);

    expect(new RepositoryInsights()->handle(new ToolRequest(['metric' => $metric])))->toContain('temporarily unavailable');
})->with(['overview', 'opportunities', 'recent_changes']);

it('refuses arbitrary repository operations without a request', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldNotReceive('contributors', 'repository', 'languages', 'searchIssues', 'closedPullRequests');

    expect(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'delete_repository'])))->toContain('Unsupported repository metric');
});
