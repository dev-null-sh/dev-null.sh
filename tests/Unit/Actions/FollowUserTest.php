<?php

declare(strict_types=1);

use App\Actions\FollowUser;
use App\Actions\UnfollowUser;
use App\Enums\NotificationType;
use App\Models\User;

it('may follow a user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(FollowUser::class);

    $action->handle($follower, $user);

    expect($follower->isFollowing($user))->toBeTrue()
        ->and($user->isFollowing($follower))->toBeFalse();
});

it('is idempotent', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(FollowUser::class);

    $action->handle($follower, $user);
    $action->handle($follower, $user);

    expect($follower->following()->count())->toBeOne();
});

it('may not follow themselves', function (): void {
    $user = User::factory()->create();

    $action = resolve(FollowUser::class);

    $action->handle($user, $user);
})->throws(InvalidArgumentException::class, 'Users cannot follow themselves.');

it('notifies the followed user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(FollowUser::class);

    $action->handle($follower, $user);

    $notification = $user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Followed)
        ->and($notification->actor_id)->toBe($follower->id)
        ->and($notification->read_at)->toBeNull();
});

it('never notifies twice about the same follower', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(FollowUser::class);

    $action->handle($follower, $user);
    $user->notifications()->update(['read_at' => now()]);

    resolve(UnfollowUser::class)->handle($follower, $user);
    $action->handle($follower, $user);
    $action->handle($follower, $user);

    expect($user->notifications()->sole()->read_at)->not->toBeNull();
});
