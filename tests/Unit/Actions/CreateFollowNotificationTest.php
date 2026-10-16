<?php

declare(strict_types=1);

use App\Actions\CreateFollowNotification;
use App\Enums\NotificationType;
use App\Models\User;

it('notifies the followed user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(CreateFollowNotification::class);

    $action->handle($follower, $user);

    $notification = $user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Followed)
        ->and($notification->actor_id)->toBe($follower->id)
        ->and($notification->commit_id)->toBeNull();
});

it('is idempotent', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(CreateFollowNotification::class);

    $action->handle($follower, $user);
    $action->handle($follower, $user);

    expect($user->notifications()->count())->toBeOne();
});

it('does not notify a user who muted the follower', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $user->muting()->attach($follower);

    resolve(CreateFollowNotification::class)->handle($follower, $user);

    expect($user->notifications()->exists())->toBeFalse();
});
