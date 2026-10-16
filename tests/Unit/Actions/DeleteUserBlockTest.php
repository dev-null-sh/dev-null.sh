<?php

declare(strict_types=1);

use App\Actions\CreateUserBlock;
use App\Actions\DeleteUserBlock;
use App\Models\User;

it('may unblock a user without restoring follows', function (): void {
    $user = User::factory()->create();
    $blocked = User::factory()->create();

    $user->following()->attach($blocked);
    resolve(CreateUserBlock::class)->handle($user, $blocked);

    resolve(DeleteUserBlock::class)->handle($user, $blocked);

    expect($user->isBlocking($blocked))->toBeFalse()
        ->and($user->isFollowing($blocked))->toBeFalse();
});
