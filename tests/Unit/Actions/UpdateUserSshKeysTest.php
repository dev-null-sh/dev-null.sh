<?php

declare(strict_types=1);

use App\Actions\UpdateUserSshKeys;
use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\Support\SshKeyPair;

it('mirrors the authentication and signing keys of the github account', function (): void {
    $user = User::factory()->create();
    $authenticationKey = SshKeyPair::generate()->publicKey;
    $signingKey = SshKeyPair::generate()->publicKey;

    fakeGitHubSshKeys($user, [$authenticationKey], [$signingKey]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->pluck('fingerprint')->sort()->values()->all())
        ->toBe(collect([$authenticationKey->fingerprint(), $signingKey->fingerprint()])->sort()->values()->all());
});

it('stores a key used for both authentication and signing once', function (): void {
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;

    fakeGitHubSshKeys($user, [$publicKey], [$publicKey]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());
});

it('removes keys that are no longer on github', function (): void {
    $user = User::factory()->create();
    $removed = SshKey::factory()->for($user)->create();
    $kept = SshKeyPair::generate()->publicKey;

    fakeGitHubSshKeys($user, [$kept]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($removed->fresh())->toBeNull()
        ->and($user->sshKeys()->sole()->fingerprint)->toBe($kept->fingerprint());
});

it('moves a key to the user that now has it on github', function (): void {
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;
    $sshKey = SshKey::factory()->withPublicKey($publicKey)->create();

    fakeGitHubSshKeys($user, [$publicKey]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($sshKey->fresh()?->user_id)->toBe($user->id);
});

it('ignores unsupported keys', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->handle]),
        "api.github.com/users/{$user->handle}/keys*" => Http::response([
            ['id' => 1, 'key' => 'ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBA=='],
            ['id' => 2, 'key' => mb_trim(sshFixture('ed25519.pub'))],
        ]),
        "api.github.com/users/{$user->handle}/ssh_signing_keys*" => Http::response([]),
    ]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->sole()->fingerprint)->toBe(SshPublicKey::fromOpenSsh(sshFixture('ed25519.pub'))->fingerprint());
});

it('looks the account up by its github id, so a renamed handle cannot be claimed', function (): void {
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle']);
    $publicKey = SshKeyPair::generate()->publicKey;

    fakeGitHubSshKeys($user, [$publicKey], handle: 'octocat');

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'old-handle'));
});

it('removes all keys when the github account is gone', function (): void {
    $user = User::factory()->create();
    SshKey::factory()->for($user)->count(2)->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['message' => 'Not Found'], 404),
    ]);

    resolve(UpdateUserSshKeys::class)->handle($user);

    expect($user->sshKeys()->count())->toBe(0);
});

it('fails when github fails', function (): void {
    $user = User::factory()->create();
    $sshKey = SshKey::factory()->for($user)->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['message' => 'Server Error'], 500),
    ]);

    expect(fn () => resolve(UpdateUserSshKeys::class)->handle($user))->toThrow(RequestException::class)
        ->and($sshKey->fresh())->not->toBeNull();
});

it('authenticates as the github oauth app when configured', function (): void {
    config([
        'services.github.client_id' => 'client-id',
        'services.github.client_secret' => 'client-secret',
    ]);

    $user = User::factory()->create();

    fakeGitHubSshKeys($user);

    resolve(UpdateUserSshKeys::class)->handle($user);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));
});

it('does not authenticate when the github oauth app is not configured', function (): void {
    config([
        'services.github.client_id' => null,
        'services.github.client_secret' => null,
    ]);

    $user = User::factory()->create();

    fakeGitHubSshKeys($user);

    resolve(UpdateUserSshKeys::class)->handle($user);

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
});

it('skips users synced in the last 5 minutes when asked to', function (): void {
    $user = User::factory()->create();

    fakeGitHubSshKeys($user);

    $action = resolve(UpdateUserSshKeys::class);

    $action->handle($user);
    $action->handle($user, unlessRecentlySynced: true);

    Http::assertSentCount(3);

    $this->travel(5)->minutes();

    $action->handle($user, unlessRecentlySynced: true);

    Http::assertSentCount(6);
});

it('always syncs when not asked to skip recently synced users', function (): void {
    $user = User::factory()->create();

    fakeGitHubSshKeys($user);

    $action = resolve(UpdateUserSshKeys::class);

    $action->handle($user);
    $action->handle($user);

    Http::assertSentCount(6);
});

it('counts a gone github account as synced', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['message' => 'Not Found'], 404),
    ]);

    $action = resolve(UpdateUserSshKeys::class);

    $action->handle($user);
    $action->handle($user, unlessRecentlySynced: true);

    Http::assertSentCount(1);
});

it('does not count a failed sync as synced', function (): void {
    $user = User::factory()->create();

    Http::fakeSequence("api.github.com/user/{$user->github_id}")
        ->push(['message' => 'Server Error'], 500)
        ->push(['login' => $user->handle]);
    Http::fake([
        "api.github.com/users/{$user->handle}/*" => Http::response([]),
    ]);

    $action = resolve(UpdateUserSshKeys::class);

    expect(fn () => $action->handle($user))->toThrow(RequestException::class);

    $action->handle($user, unlessRecentlySynced: true);

    Http::assertSentCount(4);
});
