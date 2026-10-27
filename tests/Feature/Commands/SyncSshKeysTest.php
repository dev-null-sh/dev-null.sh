<?php

declare(strict_types=1);

use App\Models\SshKey;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Tests\Support\SshKeyPair;

it('syncs the ssh keys of all users', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceKey = SshKeyPair::generate()->publicKey;
    $bobKey = SshKeyPair::generate()->publicKey;

    fakeGitHubSshKeys($alice, [$aliceKey]);
    fakeGitHubSshKeys($bob, signingKeys: [$bobKey]);

    $this->artisan('ssh-keys:sync')
        ->expectsOutputToContain('Synced the SSH keys of 2 users from GitHub.')
        ->assertSuccessful();

    expect($alice->sshKeys()->sole()->fingerprint)->toBe($aliceKey->fingerprint())
        ->and($bob->sshKeys()->sole()->fingerprint)->toBe($bobKey->fingerprint());
});

it('keeps going when the keys of a user cannot be synced', function (string $exception, Closure $response): void {
    Exceptions::fake();

    $failing = User::factory()->create(['handle' => 'failing']);
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;
    $existingKey = SshKey::factory()->for($failing)->create();

    Http::fake(["api.github.com/user/{$failing->github_id}" => $response]);
    fakeGitHubSshKeys($user, [$publicKey]);

    $this->artisan('ssh-keys:sync')
        ->expectsOutputToContain('Could not sync the SSH keys of @failing.')
        ->expectsOutputToContain('Synced the SSH keys of 1 users from GitHub.')
        ->assertFailed();

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint())
        ->and($existingKey->fresh())->not->toBeNull();

    Exceptions::assertReported($exception);
})->with([
    'server error' => [RequestException::class, fn (): Closure => fn () => Http::response(['message' => 'Server Error'], 500)],
    'connection error' => [ConnectionException::class, fn (): Closure => fn () => Http::failedConnection()],
]);

it('stops when github rate limits the requests', function (int $status): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['message' => 'API rate limit exceeded'], $status),
        '*' => Http::response(['message' => 'Unexpected request'], 500),
    ]);

    $this->artisan('ssh-keys:sync')
        ->expectsOutputToContain('GitHub rate limit reached after 0 users. Run the command again later.')
        ->assertFailed();

    Http::assertSentCount(1);

    expect($other->sshKeys()->count())->toBe(0);
})->with([403, 429]);
