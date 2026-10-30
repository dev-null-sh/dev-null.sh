<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\SshKey;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Tests\Support\SshKeyPair;

it('syncs the ssh keys of all users', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceKey = SshKeyPair::generate()->publicKey;
    $bobKey = SshKeyPair::generate()->publicKey;

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($alice->github_id)->andReturn($alice->handle);
    $github->shouldReceive('sshKeys')->with($alice->handle)->andReturn([$aliceKey->toOpenSsh()]);
    $github->shouldReceive('login')->with($bob->github_id)->andReturn($bob->handle);
    $github->shouldReceive('sshKeys')->with($bob->handle)->andReturn([$bobKey->toOpenSsh()]);

    $this->artisan('ssh-keys:sync')
        ->expectsOutputToContain('Synced the SSH keys of 2 users from GitHub.')
        ->assertSuccessful();

    expect($alice->sshKeys()->sole()->fingerprint)->toBe($aliceKey->fingerprint())
        ->and($bob->sshKeys()->sole()->fingerprint)->toBe($bobKey->fingerprint());
});

it('keeps going when the keys of a user cannot be synced', function (string $exception, Closure $throwable): void {
    Exceptions::fake();

    $failing = User::factory()->create(['handle' => 'failing']);
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;
    $existingKey = SshKey::factory()->for($failing)->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($failing->github_id)->andThrow($throwable());
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([$publicKey->toOpenSsh()]);

    $this->artisan('ssh-keys:sync')
        ->expectsOutputToContain('Could not sync the SSH keys of @failing.')
        ->expectsOutputToContain('Synced the SSH keys of 1 users from GitHub.')
        ->assertFailed();

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint())
        ->and($existingKey->fresh())->not->toBeNull();

    Exceptions::assertReported($exception);
})->with([
    'server error' => [RequestException::class, fn (): RequestException => new RequestException(new Response(new Psr7Response(500)))],
    'connection error' => [ConnectionException::class, fn (): ConnectionException => new ConnectionException()],
]);

it('stops when github rate limits the requests', function (int $status): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andThrow(new RequestException(new Response(new Psr7Response($status))));

    $this->artisan('ssh-keys:sync')
        ->expectsOutputToContain('GitHub rate limit reached after 0 users. Run the command again later.')
        ->assertFailed();

    $github->shouldHaveReceived('login')->once();

    expect($other->sshKeys()->count())->toBe(0);
})->with([403, 429]);
