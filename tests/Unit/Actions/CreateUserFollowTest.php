<?php

declare(strict_types=1);

use App\Actions\CreateUserFollow;
use App\Actions\DeleteUserFollow;
use App\Enums\NotificationType;
use App\Models\User;

it('may follow a user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(CreateUserFollow::class);

    $action->handle($follower, $user);

    expect($follower->isFollowing($user))->toBeTrue()
        ->and($user->isFollowing($follower))->toBeFalse();
});

it('is idempotent', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(CreateUserFollow::class);

    $action->handle($follower, $user);
    $action->handle($follower, $user);

    expect($follower->following()->count())->toBeOne();
});

it('notifies the followed user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(CreateUserFollow::class);

    $action->handle($follower, $user);

    $notification = $user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Followed)
        ->and($notification->actor_id)->toBe($follower->id)
        ->and($notification->read_at)->toBeNull();
});

it('never notifies twice about the same follower', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(CreateUserFollow::class);

    $action->handle($follower, $user);
    $user->notifications()->update(['read_at' => now()]);

    resolve(DeleteUserFollow::class)->handle($follower, $user);
    $action->handle($follower, $user);
    $action->handle($follower, $user);

    expect($user->notifications()->sole()->read_at)->not->toBeNull();
});
