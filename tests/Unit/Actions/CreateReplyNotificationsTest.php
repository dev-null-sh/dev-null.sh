<?php

declare(strict_types=1);

use App\Actions\CreateReplyNotifications;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;

it('notifies the parent author about approved replies', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $action = resolve(CreateReplyNotifications::class);

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

    $action = resolve(CreateReplyNotifications::class);

    $action->handle($reply);

    expect(Notification::query()->sole()->recipient_id)->toBe($parent->user_id);
});

it('does not notify about commits that are not approved replies', function (): void {
    $parent = Commit::factory()->create();

    $action = resolve(CreateReplyNotifications::class);

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

    $action = resolve(CreateReplyNotifications::class);

    $action->handle($reply);

    expect(Notification::query()->count())->toBe(0);
});

it('is idempotent', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $action = resolve(CreateReplyNotifications::class);

    $action->handle($reply);
    $action->handle($reply);

    expect(Notification::query()->count())->toBeOne();
});

it('does not notify authors who muted the replier or are across a block', function (string $relation, bool $parentAuthorActs): void {
    $parent = Commit::factory()->create();
    $silenced = Commit::factory()->for($parent, 'parent')->create();
    $notified = Commit::factory()->for($parent, 'parent')->create();

    [$actor, $subject] = $parentAuthorActs ? [$parent->user, $silenced->user] : [$silenced->user, $parent->user];
    $actor->{$relation}()->attach($subject);

    resolve(CreateReplyNotifications::class)->handle($silenced, $notified);

    $notification = Notification::query()->sole();

    expect($notification->actor_id)->toBe($notified->user_id)
        ->and($notification->commit_id)->toBe($notified->id);
})->with([
    'the author muted the replier' => ['muting', true],
    'the author blocked the replier' => ['blocking', true],
    'the replier blocked the author' => ['blocking', false],
]);

it('does nothing without commits', function (): void {
    $action = resolve(CreateReplyNotifications::class);

    $action->handle();

    expect(Notification::query()->count())->toBe(0);
});
