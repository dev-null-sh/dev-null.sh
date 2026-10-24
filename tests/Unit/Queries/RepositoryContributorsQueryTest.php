<?php

declare(strict_types=1);

use App\Queries\RepositoryContributorsQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('lists GitHub user logins and ignores bots', function (): void {
    Http::fake(['api.github.com/repos/nunomaduro/dev-null.sh/contributors*' => Http::response([
        ['type' => 'Bot', 'login' => 'dependabot[bot]', 'contributions' => 50],
        ['type' => 'User', 'login' => 'NunoMaduro', 'contributions' => 10],
        ['type' => 'User', 'login' => 'xHeaven', 'contributions' => 20],
    ])]);

    $query = resolve(RepositoryContributorsQuery::class);

    expect($query->usernames())->toBe(['nunomaduro', 'xheaven'])
        ->and($query->contains('nunomaduro'))->toBeTrue()
        ->and($query->contains('NunoMaduro'))->toBeTrue()
        ->and($query->contains('octocat'))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request['per_page'] === 100 && $request['page'] === 1);
});

it('pages through contributors until a short page', function (): void {
    $page = fn (int $number): array => collect(range(1, 100))->map(fn (int $n): array => [
        'type' => 'User',
        'login' => "dev{$number}-{$n}",
        'contributions' => 1,
    ])->all();

    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/contributors*' => fn (Request $request) => Http::response(match ((int) $request['page']) {
            1 => $page(1),
            2 => array_slice($page(2), 0, 3),
            default => [],
        }),
    ]);

    expect(resolve(RepositoryContributorsQuery::class)->usernames())->toHaveCount(103)
        ->and(resolve(RepositoryContributorsQuery::class)->contains('dev2-3'))->toBeTrue();
});

it('soft-fails to nobody when GitHub errors without caching the miss', function (): void {
    Http::fake(['api.github.com/repos/nunomaduro/dev-null.sh/contributors*' => Http::response([], 503)]);

    expect(resolve(RepositoryContributorsQuery::class)->usernames())->toBe([])
        ->and(Cache::has('repository-contributors:usernames'))->toBeFalse();
});

it('reads a warm cache without calling GitHub', function (): void {
    Cache::put('repository-contributors:usernames', ['cached'], 900);

    expect(resolve(RepositoryContributorsQuery::class)->contains('cached'))->toBeTrue();

    Http::assertNothingSent();
});

it('fetches from GitHub when the cache cannot be read', function (): void {
    Http::fake(['api.github.com/repos/nunomaduro/dev-null.sh/contributors*' => Http::response([
        ['type' => 'User', 'login' => 'octocat', 'contributions' => 1],
    ])]);

    Cache::partialMock()
        ->shouldReceive('get')
        ->once()
        ->andThrow(new RuntimeException('Cache read failed'));

    expect(resolve(RepositoryContributorsQuery::class)->usernames())->toBe(['octocat']);
});

it('returns usernames when the cache cannot be written', function (): void {
    Http::fake(['api.github.com/repos/nunomaduro/dev-null.sh/contributors*' => Http::response([
        ['type' => 'User', 'login' => 'octocat', 'contributions' => 1],
    ])]);

    Cache::partialMock()
        ->shouldReceive('put')
        ->once()
        ->andThrow(new RuntimeException('Cache write failed'));

    expect(resolve(RepositoryContributorsQuery::class)->usernames())->toBe(['octocat']);
});
