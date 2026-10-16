<?php

declare(strict_types=1);

use App\Actions\DeleteUserMute;
use App\Models\User;

it('may unmute a user', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();

    $user->muting()->attach($muted);

    resolve(DeleteUserMute::class)->handle($user, $muted);

    expect($user->isMuting($muted))->toBeFalse();
});
