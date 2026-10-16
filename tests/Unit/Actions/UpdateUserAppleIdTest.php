<?php

declare(strict_types=1);

use App\Actions\UpdateUserAppleId;
use App\Models\User;

it('links an apple id to the user', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserAppleId::class)->handle($user, '001234.0a1b2c3d4e5f.1234');

    expect($user->refresh()->apple_id)->toBe('001234.0a1b2c3d4e5f.1234')
        ->and($user->toArray())->not->toHaveKey('apple_id');
});
