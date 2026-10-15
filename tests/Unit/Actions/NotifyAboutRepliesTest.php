<?php

declare(strict_types=1);

use App\Actions\NotifyAboutReplies;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;

it('notifies the parent author about approved replies', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $action = resolve(NotifyAboutReplies::class);

    $action->handle($reply);

    $notification = $parent->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Replied)
        ->and($notification->actor_id)->toBe($reply->user_id)
        ->and($notification->commit_id)->toBe($reply->id);
});

it('notifies only the direct parent author about nested replies', function (): void {
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($root, 'parent')->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $action = resolve(NotifyAboutReplies::class);

    $action->handle($reply);

    expect(Notification::query()->sole()->recipient_id)->toBe($parent->user_id);
});

it('does not notify about commits that are not approved replies', function (): void {
    $parent = Commit::factory()->create();

    $action = resolve(NotifyAboutReplies::class);

    $action->handle(
        Commit::factory()->create(),
        Commit::factory()->pending()->for($parent, 'parent')->create(),
        Commit::factory()->rejected()->for($parent, 'parent')->create(),
    );

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify authors replying to themselves', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent->user)->for($parent, 'parent')->create();

    $action = resolve(NotifyAboutReplies::class);

    $action->handle($reply);

    expect(Notification::query()->count())->toBe(0);
});

it('is idempotent', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $action = resolve(NotifyAboutReplies::class);

    $action->handle($reply);
    $action->handle($reply);

    expect(Notification::query()->count())->toBeOne();
});

it('does nothing without commits', function (): void {
    $action = resolve(NotifyAboutReplies::class);

    $action->handle();

    expect(Notification::query()->count())->toBe(0);
});
