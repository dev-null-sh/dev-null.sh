<?php

declare(strict_types=1);

use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\User;
use Tests\Support\SshKeyPair;

it('syncs the ssh keys of the user', function (): void {
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;

    fakeGitHubSshKeys($user, [$publicKey]);

    dispatch_sync(new SyncGitHubSshKeysJob($user));

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());
});
