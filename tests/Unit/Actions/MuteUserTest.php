<?php

declare(strict_types=1);

use App\Actions\MuteUser;
use App\Actions\UnmuteUser;
use App\Models\User;

it('may mute a user without telling them or touching follows', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();

    $user->following()->attach($muted);
    $muted->following()->attach($user);

    resolve(MuteUser::class)->handle($user, $muted);

    expect($user->isMuting($muted))->toBeTrue()
        ->and($muted->isMuting($user))->toBeFalse()
        ->and($user->isFollowing($muted))->toBeTrue()
        ->and($muted->isFollowing($user))->toBeTrue()
        ->and($muted->notifications()->exists())->toBeFalse();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();

    $action = resolve(MuteUser::class);

    $action->handle($user, $muted);
    $action->handle($user, $muted);

    expect($user->muting()->count())->toBeOne();
});

it('may not mute themselves', function (): void {
    $user = User::factory()->create();

    resolve(MuteUser::class)->handle($user, $user);
})->throws(InvalidArgumentException::class, 'Users cannot mute themselves.');

it('may unmute a user', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();

    $user->muting()->attach($muted);

    resolve(UnmuteUser::class)->handle($user, $muted);

    expect($user->isMuting($muted))->toBeFalse();
});
