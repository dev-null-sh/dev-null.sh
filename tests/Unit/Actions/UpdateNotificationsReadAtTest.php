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
