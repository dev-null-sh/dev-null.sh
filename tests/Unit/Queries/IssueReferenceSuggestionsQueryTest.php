<?php

declare(strict_types=1);

use App\Queries\IssueReferenceSuggestionsQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

it('returns the exact issue or pull request and prefix matches, exact number first', function (): void {
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/issues/72' => Http::response([
            'number' => 72,
            'title' => 'Exact match',
            'html_url' => 'https://github.com/nunomaduro/dev-null.sh/issues/72',
        ]),
        'api.github.com/search/issues*' => Http::response([
            'incomplete_results' => false,
            'items' => [
                [
                    'number' => 720,
                    'title' => 'Prefix pull',
                    'html_url' => 'https://github.com/nunomaduro/dev-null.sh/pull/720',
                    'pull_request' => [],
                ],
                [
                    'number' => 72,
                    'title' => 'Exact match again',
                    'html_url' => 'https://github.com/nunomaduro/dev-null.sh/issues/72',
                ],
                [
                    'number' => 821,
                    'title' => 'Mentions 72 in the body',
                    'html_url' => 'https://github.com/nunomaduro/dev-null.sh/issues/821',
                ],
            ],
        ]),
    ]);

    $references = app(IssueReferenceSuggestionsQuery::class)->get('72');

    expect($references)->toBe([
        [
            'number' => 72,
            'title' => 'Exact match',
            'url' => 'https://github.com/nunomaduro/dev-null.sh/issues/72',
            'kind' => 'issue',
        ],
        [
            'number' => 720,
            'title' => 'Prefix pull',
            'url' => 'https://github.com/nunomaduro/dev-null.sh/pull/720',
            'kind' => 'pull',
        ],
    ]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues/72'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/search/issues')
        && $request['q'] === 'repo:nunomaduro/dev-null.sh 72'
        && $request['per_page'] === 10);
});

it('returns nothing for a non-numeric query', function (): void {
    Http::fake();

    expect(app(IssueReferenceSuggestionsQuery::class)->get('abc'))->toBe([]);

    Http::assertNothingSent();
});

it('survives GitHub failures without suggestions', function (): void {
    Http::fake([
        'api.github.com/*' => Http::response('nope', 500),
    ]);

    expect(app(IssueReferenceSuggestionsQuery::class)->get('9'))->toBe([]);
});

it('caches suggestions briefly', function (): void {
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/issues/3' => Http::response([
            'number' => 3,
            'title' => 'Cached',
            'html_url' => 'https://github.com/nunomaduro/dev-null.sh/issues/3',
            'pull_request' => ['url' => 'https://api.github.com/repos/nunomaduro/dev-null.sh/pulls/3'],
        ]),
        'api.github.com/search/issues*' => Http::response([
            'incomplete_results' => false,
            'items' => [],
        ]),
    ]);

    $query = app(IssueReferenceSuggestionsQuery::class);

    expect($query->get('3')[0])->toMatchArray([
        'number' => 3,
        'kind' => 'pull',
        'title' => 'Cached',
    ]);
    expect($query->get('3'))->toHaveCount(1);

    Http::assertSentCount(2);
});
