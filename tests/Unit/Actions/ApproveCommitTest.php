<?php

declare(strict_types=1);

use App\Actions\ApproveCommit;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

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

it('notifies mentioned users once a commit is approved', function (): void {
    $mentioned = User::factory()->create(['username' => 'jdoe']);
    $commit = Commit::factory()->pending()->create(['body' => 'Hey @jdoe']);

    $action = resolve(ApproveCommit::class);

    $action->handle($commit);
    $action->handle($commit);

    expect($mentioned->notifications()->sole())
        ->type->toBe(NotificationType::Mentioned)
        ->commit_id->toBe($commit->id);
});
