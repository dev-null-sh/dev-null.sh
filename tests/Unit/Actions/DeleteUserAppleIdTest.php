<?php

declare(strict_types=1);

use App\Actions\DeleteUserAppleId;
use App\Models\User;

it('unlinks the apple id of the user', function (): void {
    $user = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234']);

    resolve(DeleteUserAppleId::class)->handle($user);

    expect($user->refresh()->apple_id)->toBeNull();
});
