<?php

declare(strict_types=1);

use App\Models\SshKey;
use App\Models\User;
use App\Queries\SshKeyOwnerQuery;

it('finds the username of the user who owns an ssh key', function (): void {
    $sshKey = SshKey::factory()->for(User::factory()->create(['username' => 'taylor']))->create();
    SshKey::factory()->create();

    expect(resolve(SshKeyOwnerQuery::class)->find($sshKey->fingerprint))->toBe('taylor');
});

it('finds no username for an unknown ssh key', function (): void {
    SshKey::factory()->create();

    expect(resolve(SshKeyOwnerQuery::class)->find('SHA256:unknown'))->toBeNull();
});
