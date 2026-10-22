<?php

declare(strict_types=1);

use App\Ai\Tools\RepositoryInsights;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;

it('returns a bounded commit ranking without bots or unnecessary contributor data', function (): void {
    $contributors = collect(range(1, 12))->map(fn (int $number): array => [
        'type' => 'User', 'login' => "dev{$number}", 'contributions' => 100 - $number,
        'html_url' => "https://github.com/dev{$number}", 'id' => $number, 'email' => 'not-for-the-model@example.com',
    ])->all();
    Http::fake(['api.github.com/repos/dev-null-sh/dev-null.sh/contributors*' => Http::response([
        ['type' => 'Bot', 'login' => 'robot', 'contributions' => 200], ...$contributors,
    ])]);

    $result = json_decode(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'contributors', 'repository' => 'other/private'])), true);

    expect($result['contributors'])->toHaveCount(10);
    expect($result['contributors'][0])->toBe(['username' => 'dev1', 'commits' => 99, 'url' => 'https://github.com/dev1']);
    expect($result['contributors'][9]['username'])->toBe('dev10');
    expect($result)->toMatchArray(['complete' => false, 'repository' => 'dev-null-sh/dev-null.sh']);
    expect($result['coverage'])->toContain('Not an exhaustive');
    Http::assertSent(fn (Request $request): bool => $request['per_page'] === 100);
});

it('separates issues from pull requests and reads repository language bytes', function (): void {
    config(['services.github.client_id' => 'test-client', 'services.github.client_secret' => 'test-secret']);
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh' => Http::response(['stargazers_count' => 42, 'forks_count' => 9, 'open_issues_count' => 999]),
        'api.github.com/repos/dev-null-sh/dev-null.sh/languages' => Http::response(['PHP' => 300, 'TypeScript' => 100]),
        'api.github.com/search/issues*' => fn (Request $request) => Http::response([
            'total_count' => match ($request['q']) {
                'repo:dev-null-sh/dev-null.sh is:issue is:open' => 13,
                'repo:dev-null-sh/dev-null.sh is:pr is:open' => 4,
            },
            'incomplete_results' => false,
        ]),
    ]);

    $tool = new RepositoryInsights;
    $overview = json_decode($tool->handle(new ToolRequest(['metric' => 'overview'])), true);
    $languages = json_decode($tool->handle(new ToolRequest(['metric' => 'languages'])), true);

    expect($overview)->toMatchArray(['stars' => 42, 'forks' => 9, 'open_issues' => 13, 'open_pull_requests' => 4, 'complete' => true]);
    expect($languages['languages'])->toBe(['PHP' => 300, 'TypeScript' => 100]);
    expect($languages['definition'])->toContain('bytes');
    expect($tool->handle(new ToolRequest(['metric' => 'overview'])))->toContain('budget exhausted');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('test-client:test-secret')));
});

it('finds contribution opportunities using either helpful label and returns only titles and links', function (): void {
    Http::fake(['api.github.com/search/issues*' => Http::response([
        'incomplete_results' => false,
        'items' => [['title' => str_repeat('x', 400), 'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/12', 'body' => 'Not sent to the model']],
    ])]);

    $result = json_decode(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'opportunities'])), true);

    expect($result['issues'])->toBe([['title' => str_repeat('x', 300), 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/12']]);
    expect($result['complete'])->toBeFalse();
    Http::assertSent(fn (Request $request): bool => $request['q'] === 'repo:dev-null-sh/dev-null.sh is:issue is:open label:"good first issue","help wanted"'
        && $request['per_page'] === 10 && $request['sort'] === 'updated' && $request['order'] === 'desc');
});

it('returns recent production merges from the changelog without internal author data', function (): void {
    Http::fake(['api.github.com/repos/dev-null-sh/dev-null.sh/pulls*' => Http::response(collect(range(12, 1))->map(fn (int $number): array => [
        'number' => $number, 'title' => "feat: change {$number}",
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/pull/{$number}",
        'merged_at' => sprintf('2026-10-19T00:00:%02dZ', $number), 'updated_at' => sprintf('2026-10-19T00:00:%02dZ', $number),
        'merge_commit_sha' => 'abc', 'user' => ['id' => 1, 'login' => 'alice', 'avatar_url' => 'https://github.com/alice.png'],
    ])->all())]);

    $result = json_decode(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'recent_changes'])), true);

    expect($result['pull_requests'])->toHaveCount(10);
    expect($result['pull_requests'][0])->toBe([
        'title' => 'Change 12', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/12', 'author' => 'alice', 'merged_at' => '2026-10-19T00:00:12Z',
    ]);
    expect($result['definition'])->toContain('not a verified deployment time');
    Http::assertSent(fn (Request $request): bool => $request['base'] === 'production' && $request['state'] === 'closed');
});

it('keeps the original freshness timestamp until the repository cache expires', function (): void {
    Http::fake(['api.github.com/repos/dev-null-sh/dev-null.sh/languages' => Http::sequence()->push(['PHP' => 100])->push(['PHP' => 200])]);
    $request = new ToolRequest(['metric' => 'languages']);
    $first = new RepositoryInsights()->handle($request);
    $this->travel(14)->minutes();

    expect(new RepositoryInsights()->handle($request))->toBe($first);

    $this->travel(2)->minutes();
    $fresh = json_decode(new RepositoryInsights()->handle($request), true);
    expect($fresh['languages'])->toBe(['PHP' => 200]);
    expect($fresh['as_of'])->toBe(now()->toIso8601String());
    Http::assertSentCount(2);
});

it('does not turn GitHub failures into zero counts or cache the failure', function (mixed $failure): void {
    Http::fake(['api.github.com/repos/dev-null-sh/dev-null.sh/languages' => Http::sequence()->pushResponse($failure)->push(['PHP' => 123])]);
    $request = new ToolRequest(['metric' => 'languages']);
    $tool = new RepositoryInsights;

    expect($tool->handle($request))->toContain('temporarily unavailable');
    expect(json_decode($tool->handle($request), true)['languages'])->toBe(['PHP' => 123]);
})->with([
    'rate limit' => fn () => Http::response(['message' => 'secret error detail'], 403),
    'server error' => fn () => Http::response([], 503),
    'timeout' => fn () => Http::failedConnection(),
]);

it('reports unavailable changelogs and incomplete searches instead of claiming complete evidence', function (string $metric): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh' => Http::response(['stargazers_count' => 42, 'forks_count' => 9]),
        'api.github.com/repos/dev-null-sh/dev-null.sh/pulls*' => Http::response([], 503),
        'api.github.com/search/issues*' => Http::response(['total_count' => 0, 'incomplete_results' => true]),
    ]);

    expect(new RepositoryInsights()->handle(new ToolRequest(['metric' => $metric])))->toContain('temporarily unavailable');
})->with(['overview', 'opportunities', 'recent_changes']);

it('refuses arbitrary repository operations without a request', function (): void {
    expect(new RepositoryInsights()->handle(new ToolRequest(['metric' => 'delete_repository'])))->toContain('Unsupported repository metric');
    Http::assertNothingSent();
});
