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

    $follow = $follower->following()->sole()->pivot->getAttributes();

    $this->travel(1)->minute();

    $action->handle($follower, $user);

    expect($follower->following()->count())->toBeOne();
    expect($follower->following()->sole()->pivot->getAttributes())->toBe($follow);
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

    $notification = $user->notifications()->sole();
    $notification->update(['read_at' => now()]);

    resolve(DeleteUserFollow::class)->handle($follower, $user);
    $action->handle($follower, $user);
    $action->handle($follower, $user);

    expect($follower->isFollowing($user))->toBeTrue();
    expect($user->notifications()->sole()->id)->toBe($notification->id);
    expect($notification->fresh()->read_at)->not->toBeNull();
});
