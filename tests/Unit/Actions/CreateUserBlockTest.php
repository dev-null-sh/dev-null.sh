<?php

declare(strict_types=1);

use App\Actions\CreateUserBlock;
use App\Models\User;

it('may block a user, removing follows in both directions', function (): void {
    $user = User::factory()->create();
    $blocked = User::factory()->create();
    $friend = User::factory()->create();

    $user->following()->attach([$blocked->id, $friend->id]);
    $blocked->following()->attach([$user->id, $friend->id]);

    resolve(CreateUserBlock::class)->handle($user, $blocked);

    expect($user->isBlocking($blocked))->toBeTrue()
        ->and($blocked->isBlocking($user))->toBeFalse()
        ->and($user->isFollowing($blocked))->toBeFalse()
        ->and($blocked->isFollowing($user))->toBeFalse()
        ->and($user->isFollowing($friend))->toBeTrue()
        ->and($blocked->isFollowing($friend))->toBeTrue();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $blocked = User::factory()->create();

    $action = resolve(CreateUserBlock::class);

    $action->handle($user, $blocked);
    $action->handle($user, $blocked);

    expect($user->blocking()->count())->toBeOne();
});
