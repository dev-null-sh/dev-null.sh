<?php

declare(strict_types=1);

use App\Actions\DeleteStarNotification;
use App\Models\Notification;

it('deletes the notification about a star', function (): void {
    $notification = Notification::factory()->starred()->create();
    $sameCommit = Notification::factory()->for($notification->recipient, 'recipient')->starred($notification->commit)->create();
    $otherCommit = Notification::factory()->for($notification->recipient, 'recipient')->for($notification->actor, 'actor')->starred()->create();

    resolve(DeleteStarNotification::class)->handle($notification->actor->user, $notification->commit);

    expect($notification->fresh())->toBeNull()
        ->and($sameCommit->fresh())->not->toBeNull()
        ->and($otherCommit->fresh())->not->toBeNull();
});

it('keeps other notifications from the same actor about the commit', function (): void {
    $notification = Notification::factory()->replied()->create();

    resolve(DeleteStarNotification::class)->handle($notification->actor->user, $notification->commit);

    expect($notification->fresh())->not->toBeNull();
});
