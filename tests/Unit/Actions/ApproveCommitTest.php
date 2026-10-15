<?php

declare(strict_types=1);

use App\Actions\ApproveCommit;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;

it('may approve a commit', function (): void {
    $commit = Commit::factory()->pending()->create();

    $action = resolve(ApproveCommit::class);

    $action->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('notifies the parent author once a reply is approved', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->pending()->for($parent, 'parent')->create();

    $action = resolve(ApproveCommit::class);

    $action->handle($reply);
    $action->handle($reply);

    $notification = $parent->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Replied)
        ->and($notification->actor_id)->toBe($reply->user_id)
        ->and($notification->commit_id)->toBe($reply->id);
});

it('notifies the quoted author once a quote is approved', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->pending()->for($quoted, 'quotedCommit')->create();

    $action = resolve(ApproveCommit::class);

    $action->handle($quote);
    $action->handle($quote);

    $notification = $quoted->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Quoted)
        ->and($notification->actor_id)->toBe($quote->user_id)
        ->and($notification->commit_id)->toBe($quote->id);
});
