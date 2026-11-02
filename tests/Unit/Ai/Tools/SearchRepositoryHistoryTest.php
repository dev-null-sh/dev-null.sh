<?php

declare(strict_types=1);

use App\Actions\UpdateStaleHandles;
use App\Ai\Tools\SearchRepositoryHistory;
use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Tools\Request as ToolRequest;

/**
 * @param  list<array<string, mixed>>  $items
 * @return array{incomplete: bool, total: int, items: list<array<string, mixed>>}
 */
function searchResults(array $items = [], bool $incomplete = false): array
{
    return ['incomplete' => $incomplete, 'total' => count($items), 'items' => $items];
}

it('searches bounded upstream history and distinguishes authors from mergers without leaking raw identities', function (): void {
    User::factory()->create(['github_id' => '123', 'handle' => 'alice-local']);
    User::factory()->create(['github_id' => '999', 'handle' => 'alice']);
    User::factory()->notOnboarded()->create(['github_id' => '456', 'handle' => 'bob']);
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('searchIssues')
        ->with('repo:dev-null-sh/dev-null.sh is:pr is:merged base:production in:title,body "dark" "mode"', 5, null)
        ->once()
        ->andReturn(searchResults(collect(range(1, 6))->map(fn (int $number): array => [
            'title' => 'feat: '.str_repeat('x', 400), 'html_url' => "https://github.com/dev-null-sh/dev-null.sh/pull/{$number}",
            'user' => ['login' => 'alice', 'id' => 123], 'body' => 'Not for the model',
            'pull_request' => ['merged_at' => '2025-01-02T03:04:05Z'],
        ])->all()));
    $github->shouldReceive('searchCommits')
        ->with('repo:dev-null-sh/dev-null.sh merge:false "dark" "mode"', 5)
        ->once()
        ->andReturn(searchResults(collect(range(1, 6))->map(fn (int $number): array => [
            'html_url' => "https://github.com/dev-null-sh/dev-null.sh/commit/{$number}",
            'author' => $number === 2 ? null : ['login' => 'bob', 'id' => 456], 'committer' => ['login' => 'merger'],
            'commit' => ['message' => "feat: dark mode\n\nCo-authored-by: Raw Name <private@example.com>", 'author' => [
                'name' => 'Raw Name', 'email' => 'private@example.com', 'date' => '2025-01-01T03:04:05Z',
            ]],
        ])->all()));

    $result = json_decode(new SearchRepositoryHistory()->handle(new ToolRequest(['query' => ' Dark   Mode ', 'repository' => 'other/private'])), true);

    expect($result['pull_requests'])->toHaveCount(5);
    expect($result['pull_requests'][0])->toBe([
        'title' => 'feat: '.str_repeat('x', 294), 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/1',
        'pr_author' => 'alice', 'merged_at' => '2025-01-02T03:04:05Z', 'mention' => '@alice-local',
    ]);
    expect($result['commits'])->toHaveCount(5);
    expect($result['commits'][0])->toBe([
        'title' => 'feat: dark mode', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/1',
        'commit_author' => 'bob', 'authored_at' => '2025-01-01T03:04:05Z', 'mention' => null,
    ]);
    expect($result['commits'][1])->toMatchArray(['commit_author' => null, 'mention' => null]);
    expect($result)->toMatchArray(['repository' => 'dev-null-sh/dev-null.sh', 'complete' => false]);
    expect($result['coverage'])->toContain('follow-up fixes');
    expect($result['attribution'])->toContain('Neither proves sole implementation');
});

it('resolves current onboarded members even when GitHub history is cached', function (): void {
    $author = User::factory()->notOnboarded()->create(['github_id' => '123', 'handle' => 'alice']);
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('searchIssues')->andReturn(searchResults());
    $github->shouldReceive('searchCommits')->andReturn(searchResults([[
        'author' => ['login' => 'alice', 'id' => 123],
        'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/abc',
        'commit' => ['message' => 'feat: bookmarks'],
    ]]));
    $request = new ToolRequest(['query' => 'bookmarks']);

    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBeNull();

    $author->update(['onboarded_at' => now()]);
    $author->profile->update(['handle' => 'alice-renamed']);
    User::factory()->create(['github_id' => '456', 'handle' => 'alice']);
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBe('@alice-renamed');

    resolve(UpdateStaleHandles::class)->handle('alice-renamed', '789');
    $result = new SearchRepositoryHistory()->handle($request);
    expect($author->refresh()->handle)->toBe('_renamed-123');
    expect($result)->not->toContain('_renamed-123', 'github_id');
    expect(json_decode($result, true)['commits'][0])->toMatchArray(['mention' => null, 'commit_author' => 'alice']);

    $author->profile->update(['handle' => 'alice-restored']);
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBe('@alice-restored');

    $author->delete();
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBeNull();
    $github->shouldHaveReceived('searchIssues')->times(1);
    $github->shouldHaveReceived('searchCommits')->times(1);
});

it('keeps empty searches distinct from unavailable history and caches each query for fifteen minutes', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('searchIssues')->andReturn(searchResults());
    $github->shouldReceive('searchCommits')->andReturn(searchResults());
    $tool = new SearchRepositoryHistory;
    $request = new ToolRequest(['query' => 'bookmarks']);
    $first = $tool->handle($request);

    expect(json_decode($first, true))->toMatchArray(['pull_requests' => [], 'commits' => []]);
    expect(json_decode($first, true)['coverage'])->toContain('No matches does not mean');
    $this->travel(14)->minutes();
    expect($tool->handle($request))->toBe($first);
    expect($tool->handle($request))->toContain('budget exhausted');
    $github->shouldHaveReceived('searchIssues')->times(1);
    $github->shouldHaveReceived('searchCommits')->times(1);

    new SearchRepositoryHistory()->handle(new ToolRequest(['query' => 'translations']));
    $github->shouldHaveReceived('searchIssues')->times(2);
    $github->shouldHaveReceived('searchCommits')->times(2);

    $this->travel(2)->minutes();
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['as_of'])->toBe(now()->toIso8601String());
    $github->shouldHaveReceived('searchIssues')->times(3);
    $github->shouldHaveReceived('searchCommits')->times(3);
});

it('rejects empty queries and search syntax that could escape the fixed repository scope', function (string $query): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldNotReceive('searchIssues', 'searchCommits');

    expect(new SearchRepositoryHistory()->handle(new ToolRequest(['query' => $query])))->toContain('Use a short feature keyword');
})->with(['', '  ', str_repeat('x', 101), 'repo:other/private', 'dark" OR repo:other/private', 'https://example.com', 'dark\\"']);

it('reports failed or incomplete searches as unavailable and retries rather than caching them', function (string $method, Closure $failure): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive($method)->andReturnUsing($failure, fn (): array => searchResults());
    $github->shouldReceive($method === 'searchIssues' ? 'searchCommits' : 'searchIssues')->andReturn(searchResults());
    $tool = new SearchRepositoryHistory;
    $request = new ToolRequest(['query' => 'bookmarks']);

    expect($tool->handle($request))->toContain('temporarily unavailable');
    expect(json_decode($tool->handle($request), true))->toMatchArray(['pull_requests' => [], 'commits' => []]);
})->with(['searchIssues', 'searchCommits'])->with([
    'rate limit' => fn () => throw new RequestException(new Response(new Psr7Response(403, [], '{"message":"secret error detail"}'))),
    'server error' => fn () => throw new RequestException(new Response(new Psr7Response(503))),
    'timeout' => fn () => throw new ConnectionException('timed out'),
    'incomplete' => fn (): array => searchResults(incomplete: true),
]);
