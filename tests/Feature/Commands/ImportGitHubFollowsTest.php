<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Notification;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;

it('imports the github follows of every user not imported yet, without notifying anyone', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $imported = User::factory()->create(['github_follows_imported_at' => now()->subDay()]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($alice->github_id)->andReturn($alice->handle);
    $github->shouldReceive('followingIds')->with($alice->handle, 10)->andReturn([$bob->github_id, $imported->github_id]);
    $github->shouldReceive('followerIds')->with($alice->handle, 10)->andReturn([]);
    $github->shouldReceive('login')->with($bob->github_id)->andReturn($bob->handle);
    $github->shouldReceive('followingIds')->with($bob->handle, 10)->andReturn([]);
    $github->shouldReceive('followerIds')->with($bob->handle, 10)->andReturn([$alice->github_id]);

    $this->artisan('follows:import')
        ->expectsOutputToContain('Imported the GitHub follows of 2 users.')
        ->assertSuccessful();

    expect($alice->isFollowing($bob))->toBeTrue()
        ->and($alice->isFollowing($imported))->toBeTrue()
        ->and($bob->refresh()->github_follows_imported_at)->not->toBeNull()
        ->and(Notification::query()->count())->toBe(0);

    $github->shouldNotHaveReceived('login', [$imported->github_id]);
});

it('keeps going when the follows of a user cannot be imported', function (string $exception, Closure $throwable): void {
    Exceptions::fake();

    $failing = User::factory()->create(['handle' => 'failing']);
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($failing->github_id)->andThrow($throwable());
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    $this->artisan('follows:import')
        ->expectsOutputToContain('Could not import the GitHub follows of @failing.')
        ->expectsOutputToContain('Imported the GitHub follows of 1 users.')
        ->assertFailed();

    expect($failing->refresh()->github_follows_imported_at)->toBeNull()
        ->and($user->refresh()->github_follows_imported_at)->not->toBeNull();

    Exceptions::assertReported($exception);
})->with([
    'server error' => [RequestException::class, fn (): RequestException => new RequestException(new Response(new Psr7Response(500)))],
    'connection error' => [ConnectionException::class, fn (): ConnectionException => new ConnectionException()],
]);

it('stops when github rate limits the requests', function (int $status): void {
    $user = User::factory()->create();
    User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->once()->andThrow(new RequestException(new Response(new Psr7Response($status))));

    $this->artisan('follows:import')
        ->expectsOutputToContain('GitHub rate limit reached after 0 users. Run the command again later.')
        ->assertFailed();

    $github->shouldHaveReceived('login')->once();
})->with([403, 429]);
