<?php

declare(strict_types=1);

use App\Actions\BlockUser;
use App\Actions\UnblockUser;
use App\Models\User;

it('may block a user, removing follows in both directions', function (): void {
    $user = User::factory()->create();
    $blocked = User::factory()->create();
    $friend = User::factory()->create();

    $user->following()->attach([$blocked->id, $friend->id]);
    $blocked->following()->attach([$user->id, $friend->id]);

    resolve(BlockUser::class)->handle($user, $blocked);

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

    $action = resolve(BlockUser::class);

    $action->handle($user, $blocked);
    $action->handle($user, $blocked);

    expect($user->blocking()->count())->toBeOne();
});

it('may unblock a user without restoring follows', function (): void {
    $user = User::factory()->create();
    $blocked = User::factory()->create();

    $user->following()->attach($blocked);
    resolve(BlockUser::class)->handle($user, $blocked);

    resolve(UnblockUser::class)->handle($user, $blocked);

    expect($user->isBlocking($blocked))->toBeFalse()
        ->and($user->isFollowing($blocked))->toBeFalse();
});
