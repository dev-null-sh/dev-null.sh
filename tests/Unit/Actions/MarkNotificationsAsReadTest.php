<?php

declare(strict_types=1);

use App\Actions\MarkNotificationsAsRead;
use App\Models\Notification;

it('marks the given notifications as read', function (): void {
    $notifications = Notification::factory()->count(2)->create();
    $other = Notification::factory()->create();

    $action = resolve(MarkNotificationsAsRead::class);

    $action->handle(...$notifications);

    foreach ($notifications as $notification) {
        expect($notification->refresh()->read_at)->not->toBeNull();
    }

    expect($other->refresh()->read_at)->toBeNull();
});

it('keeps when notifications were first read', function (): void {
    $notification = Notification::factory()->create(['read_at' => now()->subDay()]);

    $action = resolve(MarkNotificationsAsRead::class);

    $action->handle($notification);

    expect($notification->refresh()->read_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString());
});
