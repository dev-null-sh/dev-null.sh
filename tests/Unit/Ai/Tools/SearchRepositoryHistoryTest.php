<?php

declare(strict_types=1);

use App\Actions\UpdateStaleUsernames;
use App\Ai\Tools\SearchRepositoryHistory;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;

it('searches bounded upstream history and distinguishes authors from mergers without leaking raw identities', function (): void {
    User::factory()->create(['github_id' => '123', 'username' => 'alice-local']);
    User::factory()->create(['github_id' => '999', 'username' => 'alice']);
    User::factory()->notOnboarded()->create(['github_id' => '456', 'username' => 'bob']);
    Http::fake([
        'api.github.com/search/issues*' => Http::response([
            'incomplete_results' => false,
            'items' => collect(range(1, 6))->map(fn (int $number): array => [
                'title' => 'feat: '.str_repeat('x', 400), 'html_url' => "https://github.com/nunomaduro/dev-null.sh/pull/{$number}",
                'user' => ['login' => 'alice', 'id' => 123], 'body' => 'Not for the model',
                'pull_request' => ['merged_at' => '2025-01-02T03:04:05Z'],
            ])->all(),
        ]),
        'api.github.com/search/commits*' => Http::response([
            'incomplete_results' => false,
            'items' => collect(range(1, 6))->map(fn (int $number): array => [
                'html_url' => "https://github.com/nunomaduro/dev-null.sh/commit/{$number}",
                'author' => $number === 2 ? null : ['login' => 'bob', 'id' => 456], 'committer' => ['login' => 'merger'],
                'commit' => ['message' => "feat: dark mode\n\nCo-authored-by: Raw Name <private@example.com>", 'author' => [
                    'name' => 'Raw Name', 'email' => 'private@example.com', 'date' => '2025-01-01T03:04:05Z',
                ]],
            ])->all(),
        ]),
    ]);

    $result = json_decode(new SearchRepositoryHistory()->handle(new ToolRequest(['query' => ' Dark   Mode ', 'repository' => 'other/private'])), true);

    expect($result['pull_requests'])->toHaveCount(5);
    expect($result['pull_requests'][0])->toBe([
        'title' => 'feat: '.str_repeat('x', 294), 'url' => 'https://github.com/nunomaduro/dev-null.sh/pull/1',
        'pr_author' => 'alice', 'merged_at' => '2025-01-02T03:04:05Z', 'mention' => '@alice-local',
    ]);
    expect($result['commits'])->toHaveCount(5);
    expect($result['commits'][0])->toBe([
        'title' => 'feat: dark mode', 'url' => 'https://github.com/nunomaduro/dev-null.sh/commit/1',
        'commit_author' => 'bob', 'authored_at' => '2025-01-01T03:04:05Z', 'mention' => null,
    ]);
    expect($result['commits'][1])->toMatchArray(['commit_author' => null, 'mention' => null]);
    expect($result)->toMatchArray(['repository' => 'nunomaduro/dev-null.sh', 'complete' => false]);
    expect($result['coverage'])->toContain('follow-up fixes');
    expect($result['attribution'])->toContain('Neither proves sole implementation');
    Http::assertSent(fn (Request $request): bool => $request['q'] === 'repo:nunomaduro/dev-null.sh is:pr is:merged base:production in:title,body "dark" "mode"' && $request['per_page'] === 5);
    Http::assertSent(fn (Request $request): bool => $request['q'] === 'repo:nunomaduro/dev-null.sh merge:false "dark" "mode"' && $request['per_page'] === 5);
    Http::assertSentCount(2);
});

it('resolves current onboarded members even when GitHub history is cached', function (): void {
    $author = User::factory()->notOnboarded()->create(['github_id' => '123', 'username' => 'alice']);
    Http::fake([
        'api.github.com/search/issues*' => Http::response(['items' => []]),
        'api.github.com/search/commits*' => Http::response(['items' => [[
            'author' => ['login' => 'alice', 'id' => 123],
            'html_url' => 'https://github.com/nunomaduro/dev-null.sh/commit/abc',
            'commit' => ['message' => 'feat: bookmarks'],
        ]]]),
    ]);
    $request = new ToolRequest(['query' => 'bookmarks']);

    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBeNull();

    $author->update(['onboarded_at' => now(), 'username' => 'alice-renamed']);
    User::factory()->create(['github_id' => '456', 'username' => 'alice']);
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBe('@alice-renamed');

    resolve(UpdateStaleUsernames::class)->handle('alice-renamed', '789');
    $result = new SearchRepositoryHistory()->handle($request);
    expect($author->refresh()->username)->toBe('_renamed-123');
    expect($result)->not->toContain('_renamed-123', 'github_id');
    expect(json_decode($result, true)['commits'][0])->toMatchArray(['mention' => null, 'commit_author' => 'alice']);

    $author->update(['username' => 'alice-restored']);
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBe('@alice-restored');

    $author->delete();
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['commits'][0]['mention'])->toBeNull();
    Http::assertSentCount(2);
});

it('keeps empty searches distinct from unavailable history and caches each query for fifteen minutes', function (): void {
    Http::fake([
        'api.github.com/search/issues*' => Http::response(['incomplete_results' => false, 'items' => []]),
        'api.github.com/search/commits*' => Http::response(['incomplete_results' => false, 'items' => []]),
    ]);
    $tool = new SearchRepositoryHistory;
    $request = new ToolRequest(['query' => 'bookmarks']);
    $first = $tool->handle($request);

    expect(json_decode($first, true))->toMatchArray(['pull_requests' => [], 'commits' => []]);
    expect(json_decode($first, true)['coverage'])->toContain('No matches does not mean');
    $this->travel(14)->minutes();
    expect($tool->handle($request))->toBe($first);
    expect($tool->handle($request))->toContain('budget exhausted');
    Http::assertSentCount(2);

    new SearchRepositoryHistory()->handle(new ToolRequest(['query' => 'translations']));
    Http::assertSentCount(4);

    $this->travel(2)->minutes();
    expect(json_decode(new SearchRepositoryHistory()->handle($request), true)['as_of'])->toBe(now()->toIso8601String());
    Http::assertSentCount(6);
});

it('rejects empty queries and search syntax that could escape the fixed repository scope', function (string $query): void {
    expect(new SearchRepositoryHistory()->handle(new ToolRequest(['query' => $query])))->toContain('Use a short feature keyword');
    Http::assertNothingSent();
})->with(['', '  ', str_repeat('x', 101), 'repo:other/private', 'dark" OR repo:other/private', 'https://example.com', 'dark\\"']);

it('reports failed or incomplete searches as unavailable and retries rather than caching them', function (string $endpoint, mixed $failure): void {
    $empty = ['incomplete_results' => false, 'items' => []];
    Http::fake([
        "api.github.com/search/{$endpoint}*" => Http::sequence()->pushResponse($failure)->push($empty),
        'api.github.com/search/*' => Http::response($empty),
    ]);
    $tool = new SearchRepositoryHistory;
    $request = new ToolRequest(['query' => 'bookmarks']);

    expect($tool->handle($request))->toContain('temporarily unavailable');
    expect(json_decode($tool->handle($request), true))->toMatchArray(['pull_requests' => [], 'commits' => []]);
})->with(['issues', 'commits'])->with([
    'rate limit' => fn () => Http::response(['message' => 'secret error detail'], 403),
    'server error' => fn () => Http::response([], 503),
    'timeout' => fn () => Http::failedConnection(),
    'incomplete' => fn () => Http::response(['incomplete_results' => true, 'items' => []]),
]);
