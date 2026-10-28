<?php

declare(strict_types=1);

use App\Actions\CreatePastelNotification;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

it('notifies the author of the awarded commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(CreatePastelNotification::class)->handle($user, $commit);

    $notification = $commit->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Awarded)
        ->and($notification->actor_id)->toBe($user->id)
        ->and($notification->commit_id)->toBe($commit->id);
});

it('does not notify authors about their own pastéis', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreatePastelNotification::class)->handle($commit->user, $commit);

    expect($commit->user->notifications()->exists())->toBeFalse();
});

it('notifies about a commit once, whoever gives the pastel', function (): void {
    $commit = Commit::factory()->create();

    $action = resolve(CreatePastelNotification::class);

    $user = User::factory()->create();

    $action->handle($user, $commit);
    $action->handle($user, $commit);
    $action->handle(User::factory()->create(), $commit);

    expect($commit->user->notifications()->count())->toBeOne();
});

it('notifies about each commit separately', function (): void {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $first = Commit::factory()->for($author)->create();
    $second = Commit::factory()->for($author)->create();

    $action = resolve(CreatePastelNotification::class);

    $action->handle($user, $first);
    $action->handle($user, $second);

    expect($author->notifications()->count())->toBe(2);
});
