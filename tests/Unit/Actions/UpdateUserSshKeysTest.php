<?php

declare(strict_types=1);

use App\Actions\UpdateUserSshKeys;
use App\Contracts\Services\GitHubProvider;
use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Tests\Support\SshKeyPair;

it('mirrors the authentication and signing keys of the github account', function (): void {
    $user = User::factory()->create();
    $authenticationKey = SshKeyPair::generate()->publicKey;
    $signingKey = SshKeyPair::generate()->publicKey;

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([$authenticationKey->toOpenSsh(), $signingKey->toOpenSsh()]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->pluck('fingerprint')->sort()->values()->all())
        ->toBe(collect([$authenticationKey->fingerprint(), $signingKey->fingerprint()])->sort()->values()->all());
});

it('stores a key used for both authentication and signing once', function (): void {
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([$publicKey->toOpenSsh(), $publicKey->toOpenSsh()]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());
});

it('removes keys that are no longer on github', function (): void {
    $user = User::factory()->create();
    $removed = SshKey::factory()->for($user)->create();
    $kept = SshKeyPair::generate()->publicKey;

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([$kept->toOpenSsh()]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($removed->fresh())->toBeNull()
        ->and($user->sshKeys()->sole()->fingerprint)->toBe($kept->fingerprint());
});

it('moves a key to the user that now has it on github', function (): void {
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;
    $sshKey = SshKey::factory()->withPublicKey($publicKey)->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([$publicKey->toOpenSsh()]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($sshKey->fresh()?->user_id)->toBe($user->id);
});

it('ignores unsupported keys', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([
        'ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBA==',
        mb_trim(sshFixture('ed25519.pub')),
    ]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->sole()->fingerprint)->toBe(SshPublicKey::fromOpenSsh(sshFixture('ed25519.pub'))->fingerprint());
});

it('looks the account up by its github id, so a renamed handle cannot be claimed', function (): void {
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle']);
    $publicKey = SshKeyPair::generate()->publicKey;

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([$publicKey->toOpenSsh()]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());

    $github->shouldNotHaveReceived('sshKeys', ['old-handle']);
});

it('removes all keys when the github account is gone', function (): void {
    $user = User::factory()->create();
    SshKey::factory()->for($user)->count(2)->create();

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->count())->toBe(0);
});

it('fails when github fails', function (): void {
    $user = User::factory()->create();
    $sshKey = SshKey::factory()->for($user)->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andThrow(new ConnectionException());

    expect(fn () => resolve(UpdateUserSshKeys::class)->handle($user))->toThrow(ConnectionException::class)
        ->and($sshKey->fresh())->not->toBeNull();
});

it('skips users synced in the last 5 minutes when asked to', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([]);

    $action = resolve(UpdateUserSshKeys::class);

    $action->handle($user);
    $action->handle($user, unlessRecentlySynced: true);

    $github->shouldHaveReceived('sshKeys')->once();

    $this->travel(5)->minutes();

    $action->handle($user, unlessRecentlySynced: true);

    $github->shouldHaveReceived('sshKeys')->twice();
});

it('always syncs when not asked to skip recently synced users', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([]);

    $action = resolve(UpdateUserSshKeys::class);

    $action->handle($user);
    $action->handle($user);

    $github->shouldHaveReceived('sshKeys')->twice();
});

it('counts a gone github account as synced', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturnNull();

    $action = resolve(UpdateUserSshKeys::class);

    $action->handle($user);
    $action->handle($user, unlessRecentlySynced: true);

    $github->shouldHaveReceived('login')->once();
});

it('does not count a failed sync as synced', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andThrow(new ConnectionException())->once();
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([]);

    $action = resolve(UpdateUserSshKeys::class);

    expect(fn () => $action->handle($user))->toThrow(ConnectionException::class);

    $action->handle($user, unlessRecentlySynced: true);

    $github->shouldHaveReceived('sshKeys')->once();
});
