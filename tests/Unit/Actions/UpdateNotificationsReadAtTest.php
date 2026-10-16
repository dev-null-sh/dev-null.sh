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

    $action->handle($user);

    foreach ($notifications as $notification) {
        expect($notification->refresh()->read_at)->not->toBeNull();
    }

    expect($other->refresh()->read_at)->toBeNull();
});

it('keeps when notifications were first read', function (): void {
    $notification = Notification::factory()->create(['read_at' => now()->subDay()]);

    $action = resolve(UpdateNotificationsReadAt::class);

    $action->handle($notification->recipient);

    expect($notification->refresh()->read_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString());
});

it('marks every relevant group of the user read without acknowledging another recipient', function (): void {
    $starred = Notification::factory()->starred()->create();
    $sameCommit = Notification::factory()->for($starred->recipient, 'recipient')->starred($starred->commit)->create();
    $replied = Notification::factory()->replied()->create();
    $sameParent = Notification::factory()->for($replied->recipient, 'recipient')->replied($replied->commit->parent)->create();
    $otherCommit = Notification::factory()->for($starred->recipient, 'recipient')->starred()->create();
    $follow = Notification::factory()->for($starred->recipient, 'recipient')->create();

    resolve(UpdateNotificationsReadAt::class)->handle($starred->recipient);

    expect($sameCommit->refresh()->read_at)->not->toBeNull()
        ->and($replied->refresh()->read_at)->toBeNull()
        ->and($sameParent->refresh()->read_at)->toBeNull()
        ->and($otherCommit->refresh()->read_at)->not->toBeNull()
        ->and($follow->refresh()->read_at)->not->toBeNull();
});

it('marks nothing for a user without notifications', function (): void {
    $notification = Notification::factory()->create();

    resolve(UpdateNotificationsReadAt::class)->handle(User::factory()->create());

    expect($notification->refresh()->read_at)->toBeNull();
});
