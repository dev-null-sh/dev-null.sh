<?php

declare(strict_types=1);

use App\Models\SshKey;
use App\Models\User;

beforeEach(function (): void {
    config(['ssh.lookup_token' => 'secret-token']);
});

it('answers with the username that owns an ssh key', function (): void {
    $sshKey = SshKey::factory()->for(User::factory()->create(['username' => 'taylor']))->create();

    $this->withToken('secret-token')
        ->getJson(route('ssh-key-owner.show', ['fingerprint' => $sshKey->fingerprint]))
        ->assertOk()
        ->assertExactJson(['username' => 'taylor']);
});

it('answers without a username for an unknown ssh key', function (): void {
    $this->withToken('secret-token')
        ->getJson(route('ssh-key-owner.show', ['fingerprint' => 'SHA256:unknown']))
        ->assertOk()
        ->assertExactJson(['username' => null]);
});

it('requires a fingerprint', function (): void {
    $this->withToken('secret-token')
        ->getJson(route('ssh-key-owner.show'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fingerprint');
});

it('requires the lookup token', function (?string $token): void {
    $sshKey = SshKey::factory()->create();

    $request = $token === null ? $this : $this->withToken($token);

    $request->getJson(route('ssh-key-owner.show', ['fingerprint' => $sshKey->fingerprint]))
        ->assertForbidden();
})->with([
    'missing' => null,
    'wrong' => 'wrong-token',
]);

it('refuses every lookup when no token is configured', function (): void {
    config(['ssh.lookup_token' => null]);

    $this->getJson(route('ssh-key-owner.show', ['fingerprint' => SshKey::factory()->create()->fingerprint]))
        ->assertForbidden();
});
