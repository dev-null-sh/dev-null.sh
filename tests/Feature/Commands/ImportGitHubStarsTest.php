<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

it('imports the github stars of every user not imported yet', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $imported = User::factory()->create(['github_stars_imported_at' => now()->subDay()]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('login')->with($alice->github_id)->andReturn($alice->handle);
    $github->shouldReceive('starredOwnerIds')->with($alice->handle, 3)->andReturn([$bob->github_id]);
    $github->shouldReceive('login')->with($bob->github_id)->andReturn($bob->handle);
    $github->shouldReceive('starredOwnerIds')->with($bob->handle, 3)->andReturn([]);

    $this->artisan('stars:import')
        ->expectsOutputToContain('Imported the GitHub stars of 2 users.')
        ->assertSuccessful();

    expect(DB::table('starred_github_owners')->where('user_id', $alice->id)->pluck('github_id')->all())->toBe([$bob->github_id])
        ->and($bob->refresh()->github_stars_imported_at)->not->toBeNull();

    $github->shouldNotHaveReceived('login', [$imported->github_id]);
});

it('keeps going when the stars of a user cannot be imported', function (string $exception, Closure $throwable): void {
    Exceptions::fake();

    $failing = User::factory()->create(['handle' => 'failing']);
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('login')->with($failing->github_id)->andThrow($throwable());
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('starredOwnerIds')->with($user->handle, 3)->andReturn([]);

    $this->artisan('stars:import')
        ->expectsOutputToContain('Could not import the GitHub stars of @failing.')
        ->expectsOutputToContain('Imported the GitHub stars of 1 users.')
        ->assertFailed();

    expect($failing->refresh()->github_stars_imported_at)->toBeNull()
        ->and($user->refresh()->github_stars_imported_at)->not->toBeNull();

    Exceptions::assertReported($exception);
})->with([
    'server error' => [RequestException::class, fn (): RequestException => new RequestException(new Response(new Psr7Response(500)))],
    'connection error' => [ConnectionException::class, fn (): ConnectionException => new ConnectionException()],
]);

it('stops when github rate limits the requests', function (int $status): void {
    $user = User::factory()->create();
    User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('login')->with($user->github_id)->once()->andThrow(new RequestException(new Response(new Psr7Response($status))));

    $this->artisan('stars:import')
        ->expectsOutputToContain('GitHub rate limit reached after 0 users. Run the command again later.')
        ->assertFailed();

    $github->shouldHaveReceived('login')->once();
})->with([403, 429]);

it('stops before using up the github requests the rest of the app needs, and says when to run it again', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00', 'UTC'));

    $imported = User::factory()->create();
    User::factory()->count(2)->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturn(
        ['remaining' => 1504, 'limit' => 5000, 'resets_at' => CarbonImmutable::parse('2026-11-01 10:42:00', 'UTC')],
        ['remaining' => 1503, 'limit' => 5000, 'resets_at' => CarbonImmutable::parse('2026-11-01 10:42:00', 'UTC')],
    );
    $github->shouldReceive('login')->with($imported->github_id)->once()->andReturn($imported->handle);
    $github->shouldReceive('starredOwnerIds')->with($imported->handle, 3)->andReturn([]);

    $this->artisan('stars:import')
        ->expectsOutputToContain('Stopped after 1 users, to keep 1500 GitHub requests for the rest of the app: 1503 left.')
        ->expectsOutputToContain('2 users left. Run the command again after 2026-11-01 10:42:00 UTC (42 minutes from now), when the GitHub rate limit resets.')
        ->assertFailed();

    $github->shouldHaveReceived('login')->once();
});
