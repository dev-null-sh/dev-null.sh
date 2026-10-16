<?php

declare(strict_types=1);

use App\Actions\CreateStarNotification;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

it('notifies the author of the starred commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(CreateStarNotification::class)->handle($user, $commit);

    $notification = $commit->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Starred)
        ->and($notification->actor_id)->toBe($user->id)
        ->and($notification->commit_id)->toBe($commit->id);
});

it('does not notify authors about their own stars', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateStarNotification::class)->handle($commit->user, $commit);

    expect($commit->user->notifications()->exists())->toBeFalse();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $action = resolve(CreateStarNotification::class);

    $action->handle($user, $commit);
    $action->handle($user, $commit);

    expect($commit->user->notifications()->count())->toBeOne();
});

it('notifies once per stargazer', function (): void {
    $commit = Commit::factory()->create();

    $action = resolve(CreateStarNotification::class);

    $action->handle(User::factory()->create(), $commit);
    $action->handle(User::factory()->create(), $commit);

    expect($commit->user->notifications()->count())->toBe(2);
});
