<?php

declare(strict_types=1);

use App\Queries\ChangelogQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function pullRequest(int $number, string $title, ?string $mergedAt, string $login = 'xHeaven'): array
{
    return [
        'number' => $number,
        'title' => $title,
        'html_url' => "https://github.com/nunomaduro/dev-null.sh/pull/{$number}",
        'merged_at' => $mergedAt,
        'merge_commit_sha' => mb_str_pad((string) $number, 40, 'a'),
        'user' => ['login' => $login, 'avatar_url' => "https://avatars.githubusercontent.com/u/{$number}?v=4"],
    ];
}

it('lists the pull requests merged into production, newest merge first, with their parsed titles', function (): void {
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/pulls*' => Http::response([
            pullRequest(310, 'feat: dim other commits on mouse hover', '2026-10-17T23:26:43Z', 'simonhamp'),
            pullRequest(400, 'docs: closed without merging', null),
            pullRequest(377, 'feat!: turn emoji shortcodes into emoji in remotes', '2026-10-18T00:53:44Z'),
            pullRequest(304, 'Fix GitHub website URLs with uppercase schemes', '2026-10-17T22:15:34Z', 'ahosker'),
        ]),
    ]);

    expect(resolve(ChangelogQuery::class)->get())->toBe([
        [
            'number' => 377,
            'type' => 'feat',
            'breaking' => true,
            'title' => 'Turn emoji shortcodes into emoji in remotes',
            'url' => 'https://github.com/nunomaduro/dev-null.sh/pull/377',
            'sha' => mb_str_pad('377', 40, 'a'),
            'commit_url' => 'https://github.com/nunomaduro/dev-null.sh/commit/'.mb_str_pad('377', 40, 'a'),
            'merged_at' => '2026-10-18T00:53:44Z',
            'author' => ['login' => 'xHeaven', 'avatar' => 'https://avatars.githubusercontent.com/u/377?v=4'],
        ],
        [
            'number' => 310,
            'type' => 'feat',
            'breaking' => false,
            'title' => 'Dim other commits on mouse hover',
            'url' => 'https://github.com/nunomaduro/dev-null.sh/pull/310',
            'sha' => mb_str_pad('310', 40, 'a'),
            'commit_url' => 'https://github.com/nunomaduro/dev-null.sh/commit/'.mb_str_pad('310', 40, 'a'),
            'merged_at' => '2026-10-17T23:26:43Z',
            'author' => ['login' => 'simonhamp', 'avatar' => 'https://avatars.githubusercontent.com/u/310?v=4'],
        ],
        [
            'number' => 304,
            'type' => null,
            'breaking' => false,
            'title' => 'Fix GitHub website URLs with uppercase schemes',
            'url' => 'https://github.com/nunomaduro/dev-null.sh/pull/304',
            'sha' => mb_str_pad('304', 40, 'a'),
            'commit_url' => 'https://github.com/nunomaduro/dev-null.sh/commit/'.mb_str_pad('304', 40, 'a'),
            'merged_at' => '2026-10-17T22:15:34Z',
            'author' => ['login' => 'ahosker', 'avatar' => 'https://avatars.githubusercontent.com/u/304?v=4'],
        ],
    ]);

    Http::assertSent(fn (Request $request): bool => $request['state'] === 'closed'
        && $request['base'] === 'production'
        && $request['sort'] === 'updated'
        && $request['direction'] === 'desc'
        && $request['per_page'] === 100);
});

it('asks GitHub at most once every ten minutes', function (): void {
    Http::fake(['api.github.com/*' => Http::response([pullRequest(377, 'feat: emoji', '2026-10-18T00:53:44Z')])]);

    $query = resolve(ChangelogQuery::class);
    $query->get();
    $query->get();

    Http::assertSentCount(1);

    $this->travel(11)->minutes();
    $query->get();

    Http::assertSentCount(2);
});

it('is null when GitHub cannot be reached, and asks again next time', function (mixed $response): void {
    Http::fake(['api.github.com/*' => Http::sequence()->pushResponse($response)->push([pullRequest(377, 'feat: emoji', '2026-10-18T00:53:44Z')])]);

    $query = resolve(ChangelogQuery::class);

    expect($query->get())->toBeNull()
        ->and($query->get())->toHaveCount(1);
})->with([
    'an error' => fn () => Http::response(['message' => 'API rate limit exceeded'], 403),
    'a timeout' => fn () => Http::failedConnection(),
    'something unexpected' => fn () => Http::response(['message' => 'Not Found']),
]);
