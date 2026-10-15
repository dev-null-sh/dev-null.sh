<?php

declare(strict_types=1);

use App\Models\SshKey;
use App\Models\User;

test('to array', function (): void {
    $sshKey = SshKey::factory()->create()->refresh();

    expect(array_keys($sshKey->toArray()))
        ->toBe([
            'id',
            'user_id',
            'fingerprint',
            'created_at',
            'updated_at',
        ]);
});

it('belongs to a user', function (): void {
    $user = User::factory()->create();
    $sshKey = SshKey::factory()->for($user)->create();

    expect($sshKey->user->is($user))->toBeTrue();
});
