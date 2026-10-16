<?php

declare(strict_types=1);

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;

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

it('notifies the quoted author once a quote is approved', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->pending()->for($quoted, 'quotedCommit')->create();

    $action = resolve(UpdateCommitStatus::class);

    $action->handle($quote, CommitStatus::Approved);
    $action->handle($quote, CommitStatus::Approved);

    $notification = $quoted->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Quoted)
        ->and($notification->actor_id)->toBe($quote->user_id)
        ->and($notification->commit_id)->toBe($quote->id);
});
