<?php

declare(strict_types=1);

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;

it('may approve a commit', function (): void {
    $commit = Commit::factory()->pending()->create();

    $action = resolve(UpdateCommitStatus::class);

    $action->handle($commit, CommitStatus::Approved);

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('may reject a commit', function (): void {
    $commit = Commit::factory()->pending()->create();

    $action = resolve(UpdateCommitStatus::class);

    $action->handle($commit, CommitStatus::Rejected, 'Please add context.');

    $notification = $commit->user->notifications()->sole();

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected)
        ->and($notification->type)->toBe(NotificationType::Rejected)
        ->and($notification->reason)->toBe('Please add context.');
});

it('notifies the parent author once a reply is approved', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->pending()->for($parent, 'parent')->create();

    $action = resolve(UpdateCommitStatus::class);

    $action->handle($reply, CommitStatus::Approved);
    $action->handle($reply, CommitStatus::Approved);

    $notification = $parent->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Replied)
        ->and($notification->actor_id)->toBe($reply->user_id)
        ->and($notification->commit_id)->toBe($reply->id);
});

it('may put a moderated commit back in the queue, taking back what its author was told', function (): void {
    $commit = Commit::factory()->pending()->create();
    $reply = Notification::factory()->create(['type' => NotificationType::Replied, 'commit_id' => $commit->id]);

    $action = resolve(UpdateCommitStatus::class);

    $action->handle($commit, CommitStatus::Rejected, 'Looks like spam.');
    $action->handle($commit, CommitStatus::Pending);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending)
        ->and(Notification::query()->pluck('id')->all())->toBe([$reply->id]);
});
