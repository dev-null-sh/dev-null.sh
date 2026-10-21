<?php

declare(strict_types=1);

use App\Actions\UpdateRepositoryContributors;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('recognizes repository contributions by immutable github id', function (string $source, string $actor): void {
    config(['services.github.repository' => 'example/upstream']);
    $contributor = User::factory()->create(['github_id' => '42', 'username' => 'old-login']);
    $impostor = User::factory()->create(['username' => 'new-login']);

    Http::fake(fn (Request $request) => Http::response(
        parse_url($request->url(), PHP_URL_PATH) === '/repos/example/upstream/'.$source
            ? [[$actor => ['id' => 42, 'login' => 'new-login'], 'state' => 'closed']]
            : [],
    ));

    expect(resolve(UpdateRepositoryContributors::class)->handle())->toBe(1)
        ->and($contributor->refresh()->is_contributor)->toBeTrue()
        ->and($impostor->refresh()->is_contributor)->toBeFalse();

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/repos/example/upstream/issues'
        && $request['state'] === 'all'
        && $request['per_page'] === 100);
})->with([
    'issue or pull request' => ['issues', 'user'],
    'commit' => ['commits', 'author'],
    'issue or pull request discussion' => ['issues/comments', 'user'],
    'inline review comment' => ['pulls/comments', 'user'],
]);

it('reads beyond the first page of every contribution source', function (string $source, string $actor): void {
    $contributor = User::factory()->create(['github_id' => '42']);

    Http::fake(function (Request $request) use ($source, $actor) {
        if (parse_url($request->url(), PHP_URL_PATH) !== '/repos/nunomaduro/dev-null.sh/'.$source) {
            return Http::response([]);
        }

        return Http::response($request['page'] === 1
            ? array_fill(0, 100, [$actor => ['id' => 999]])
            : [[$actor => ['id' => 42]]]);
    });

    expect(resolve(UpdateRepositoryContributors::class)->handle())->toBe(1)
        ->and($contributor->refresh()->is_contributor)->toBeTrue();

    Http::assertSentCount(5);
})->with([
    ['issues', 'user'],
    ['commits', 'author'],
    ['issues/comments', 'user'],
    ['pulls/comments', 'user'],
]);

it('is idempotent and ignores unlinked commit authors and unknown accounts', function (): void {
    $contributor = User::factory()->create(['github_id' => '42']);
    $other = User::factory()->create();

    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/commits*' => Http::response([
            ['author' => null],
            ['author' => ['id' => 999]],
            ['author' => ['id' => 42]],
            ['author' => ['id' => 42]],
        ]),
        '*' => Http::response([]),
    ]);

    $action = resolve(UpdateRepositoryContributors::class);

    expect($action->handle())->toBe(1)
        ->and($action->handle())->toBe(0)
        ->and($contributor->refresh()->is_contributor)->toBeTrue()
        ->and($other->refresh()->is_contributor)->toBeFalse();
});

it('keeps earned status when activity disappears from github', function (): void {
    $contributor = User::factory()->create(['is_contributor' => true]);
    Http::fake(['*' => Http::response([])]);

    expect(resolve(UpdateRepositoryContributors::class)->handle())->toBe(0)
        ->and($contributor->refresh()->is_contributor)->toBeTrue();
});

it('propagates failed github reads without revoking an earned badge', function (Closure $response, string $exception): void {
    $contributor = User::factory()->create(['is_contributor' => true]);
    Http::fake(['*' => $response]);

    expect(fn () => resolve(UpdateRepositoryContributors::class)->handle())->toThrow($exception)
        ->and($contributor->refresh()->is_contributor)->toBeTrue();
})->with([
    'rate limited' => [fn () => Http::response([], 429), RequestException::class],
    'forbidden' => [fn () => Http::response([], 403), RequestException::class],
    'server error' => [fn () => Http::response([], 500), RequestException::class],
    'connection error' => [fn () => throw new ConnectionException('timeout'), ConnectionException::class],
]);
