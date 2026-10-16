<?php

declare(strict_types=1);

use App\Actions\UpdateNotificationsReadAt;
use App\Models\Notification;

it('marks the given notifications as read', function (): void {
    $notifications = Notification::factory()->count(2)->create();
    $other = Notification::factory()->create();

    $action = resolve(UpdateNotificationsReadAt::class);

    $action->handle(...$notifications);

    foreach ($notifications as $notification) {
        expect($notification->refresh()->read_at)->not->toBeNull();
    }

    expect($other->refresh()->read_at)->toBeNull();
});

it('keeps when notifications were first read', function (): void {
    $notification = Notification::factory()->create(['read_at' => now()->subDay()]);

    $action = resolve(UpdateNotificationsReadAt::class);

    $action->handle($notification);

    expect($notification->refresh()->read_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString());
});

it('marks every notification grouped with the given ones as read', function (): void {
    $starred = Notification::factory()->starred()->create();
    $sameCommit = Notification::factory()->for($starred->recipient, 'recipient')->starred($starred->commit)->create();
    $replied = Notification::factory()->replied()->create();
    $sameParent = Notification::factory()->for($replied->recipient, 'recipient')->replied($replied->commit->parent)->create();
    $otherCommit = Notification::factory()->for($starred->recipient, 'recipient')->starred()->create();
    $follow = Notification::factory()->for($starred->recipient, 'recipient')->create();

    resolve(UpdateNotificationsReadAt::class)->handle($starred, $replied);

    expect($sameCommit->refresh()->read_at)->not->toBeNull()
        ->and($sameParent->refresh()->read_at)->not->toBeNull()
        ->and($otherCommit->refresh()->read_at)->toBeNull()
        ->and($follow->refresh()->read_at)->toBeNull();
});

it('marks nothing without notifications', function (): void {
    $notification = Notification::factory()->create();

    resolve(UpdateNotificationsReadAt::class)->handle();

    expect($notification->refresh()->read_at)->toBeNull();
});
