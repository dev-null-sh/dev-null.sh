<?php

declare(strict_types=1);

use App\Actions\UpdateNotificationsReadAt;
use App\Models\Notification;
use App\Models\User;

it('marks all relevant notifications of the user as read', function (): void {
    $user = User::factory()->create();
    $notifications = Notification::factory()->for($user, 'recipient')->count(2)->create();
    $other = Notification::factory()->create();

    $action = resolve(UpdateNotificationsReadAt::class);

    $action->handle($user, 'all');

    foreach ($notifications as $notification) {
        expect($notification->refresh()->read_at)->not->toBeNull();
    }

    expect($other->refresh()->read_at)->toBeNull();
});

it('returns the moment it marked notifications read', function (): void {
    $notification = Notification::factory()->create();

    $readAt = resolve(UpdateNotificationsReadAt::class)->handle($notification->recipient, 'all');

    expect($readAt->toJSON())->toBe(now()->startOfSecond()->toJSON())
        ->and($notification->refresh()->read_at->equalTo($readAt))->toBeTrue();
});

it('keeps when notifications were first read', function (): void {
    $notification = Notification::factory()->create(['read_at' => now()->subDay()]);

    $action = resolve(UpdateNotificationsReadAt::class);

    $action->handle($notification->recipient, 'all');

    expect($notification->refresh()->read_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString());
});

it('marks every relevant group of the user read without acknowledging another recipient', function (): void {
    $starred = Notification::factory()->starred()->create();
    $sameCommit = Notification::factory()->for($starred->recipient, 'recipient')->starred($starred->commit)->create();
    $replied = Notification::factory()->replied()->create();
    $sameParent = Notification::factory()->for($replied->recipient, 'recipient')->replied($replied->commit->parent)->create();
    $otherCommit = Notification::factory()->for($starred->recipient, 'recipient')->starred()->create();
    $follow = Notification::factory()->for($starred->recipient, 'recipient')->create();

    resolve(UpdateNotificationsReadAt::class)->handle($starred->recipient, 'all');

    expect($sameCommit->refresh()->read_at)->not->toBeNull()
        ->and($replied->refresh()->read_at)->toBeNull()
        ->and($sameParent->refresh()->read_at)->toBeNull()
        ->and($otherCommit->refresh()->read_at)->not->toBeNull()
        ->and($follow->refresh()->read_at)->not->toBeNull();
});

it('marks nothing for a user without notifications', function (): void {
    $notification = Notification::factory()->create();

    resolve(UpdateNotificationsReadAt::class)->handle(User::factory()->create(), 'all');

    expect($notification->refresh()->read_at)->toBeNull();
});

it('marks only the types the filter shows, and nothing under none', function (): void {
    $user = User::factory()->create();
    $reply = Notification::factory()->for($user, 'recipient')->replied()->create();
    $star = Notification::factory()->for($user, 'recipient')->starred()->create();
    $follow = Notification::factory()->for($user, 'recipient')->create();

    $action = resolve(UpdateNotificationsReadAt::class);

    $action->handle($user, 'none');

    expect($reply->refresh()->read_at)->toBeNull()
        ->and($star->refresh()->read_at)->toBeNull()
        ->and($follow->refresh()->read_at)->toBeNull();

    $action->handle($user, 'replies,stars');

    expect($reply->refresh()->read_at)->not->toBeNull()
        ->and($star->refresh()->read_at)->not->toBeNull()
        ->and($follow->refresh()->read_at)->toBeNull();
});
