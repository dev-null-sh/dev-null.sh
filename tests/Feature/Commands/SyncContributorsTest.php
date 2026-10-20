<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

it('backfills contributor badges for existing accounts', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/issues?*' => Http::response([['user' => ['id' => 42]]]),
        '*' => Http::response([]),
    ]);

    $this->artisan('contributors:sync')
        ->expectsOutputToContain('Awarded the contributor badge to 1 users.')
        ->assertSuccessful();

    expect($user->refresh()->is_contributor)->toBeTrue();
});

it('reports failed synchronization so it can be retried', function (Closure $response, string $exception): void {
    Exceptions::fake();
    Http::fake(['*' => $response]);

    $this->artisan('contributors:sync')
        ->expectsOutputToContain('Could not sync repository contributors from GitHub. Try again later.')
        ->assertFailed();

    Exceptions::assertReported($exception);
})->with([
    'rate limit' => [fn () => Http::response([], 429), RequestException::class],
    'connection' => [fn () => throw new ConnectionException('timeout'), ConnectionException::class],
]);
