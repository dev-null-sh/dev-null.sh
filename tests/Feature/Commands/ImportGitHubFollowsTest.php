<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

it('imports the github follows of every user not imported yet, without notifying anyone', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $imported = User::factory()->create(['github_follows_imported_at' => now()->subDay()]);

    fakeGitHubFollows($alice, following: [$bob, $imported]);
    fakeGitHubFollows($bob, followers: [$alice]);

    $this->artisan('follows:import')
        ->expectsOutputToContain('Imported the GitHub follows of 2 users.')
        ->assertSuccessful();

    expect($alice->isFollowing($bob))->toBeTrue()
        ->and($alice->isFollowing($imported))->toBeTrue()
        ->and($bob->refresh()->github_follows_imported_at)->not->toBeNull()
        ->and(Notification::query()->count())->toBe(0);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), "user/{$imported->github_id}"));
});

it('keeps going when the follows of a user cannot be imported', function (string $exception, Closure $response): void {
    Exceptions::fake();

    $failing = User::factory()->create(['username' => 'failing']);
    $user = User::factory()->create();

    Http::fake(["api.github.com/user/{$failing->github_id}" => $response]);
    fakeGitHubFollows($user);

    $this->artisan('follows:import')
        ->expectsOutputToContain('Could not import the GitHub follows of @failing.')
        ->expectsOutputToContain('Imported the GitHub follows of 1 users.')
        ->assertFailed();

    expect($failing->refresh()->github_follows_imported_at)->toBeNull()
        ->and($user->refresh()->github_follows_imported_at)->not->toBeNull();

    Exceptions::assertReported($exception);
})->with([
    'server error' => [RequestException::class, fn (): Closure => fn () => Http::response(['message' => 'Server Error'], 500)],
    'connection error' => [ConnectionException::class, fn (): Closure => fn () => Http::failedConnection()],
]);

it('keeps going when github cannot be reached for a user', function (): void {
    Exceptions::fake();

    $unreachable = User::factory()->create(['username' => 'unreachable']);
    $user = User::factory()->create();

    Http::fake(["api.github.com/user/{$unreachable->github_id}" => Http::failedConnection()]);
    fakeGitHubFollows($user);

    $this->artisan('follows:import')
        ->expectsOutputToContain('Could not import the GitHub follows of @unreachable.')
        ->expectsOutputToContain('Imported the GitHub follows of 1 users.')
        ->assertFailed();

    expect($unreachable->refresh()->github_follows_imported_at)->toBeNull()
        ->and($user->refresh()->github_follows_imported_at)->not->toBeNull();

    Exceptions::assertReported(ConnectionException::class);
});

it('stops when github rate limits the requests', function (int $status): void {
    $user = User::factory()->create();
    User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['message' => 'API rate limit exceeded'], $status),
        '*' => Http::response(['message' => 'Unexpected request'], 500),
    ]);

    $this->artisan('follows:import')
        ->expectsOutputToContain('GitHub rate limit reached after 0 users. Run the command again later.')
        ->assertFailed();

    Http::assertSentCount(1);
})->with([403, 429]);
