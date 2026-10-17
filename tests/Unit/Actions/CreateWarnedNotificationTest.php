<?php

declare(strict_types=1);

use App\Actions\CreateWarnedNotification;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;

it('warns the author of the commit', function (): void {
    $commit = Commit::factory()->create(['disparages_php' => true]);

    $action = resolve(CreateWarnedNotification::class);

    $action->handle($commit);

    $notification = Notification::query()->sole();

    expect($notification->type)->toBe(NotificationType::Warned)
        ->and($notification->recipient_id)->toBe($commit->user_id)
        ->and($notification->actor_id)->toBe($commit->user_id)
        ->and($notification->commit_id)->toBe($commit->id);
});

it('is idempotent', function (): void {
    $commit = Commit::factory()->create(['disparages_php' => true]);

    $action = resolve(CreateWarnedNotification::class);

    $action->handle($commit);
    $action->handle($commit);

    expect(Notification::query()->count())->toBe(1);
});

it('warns nobody about an authorless commit', function (): void {
    $commit = Commit::factory()->create(['user_id' => null, 'disparages_php' => true]);

    $action = resolve(CreateWarnedNotification::class);

    $action->handle($commit);

    expect(Notification::query()->exists())->toBeFalse();
});
