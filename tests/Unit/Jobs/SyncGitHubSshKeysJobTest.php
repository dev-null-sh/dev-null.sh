<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\User;
use Tests\Support\SshKeyPair;

it('syncs the ssh keys of the user', function (): void {
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('sshKeys')->with($user->handle)->andReturn([$publicKey->toOpenSsh()]);

    dispatch_sync(new SyncGitHubSshKeysJob($user));

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());
});
