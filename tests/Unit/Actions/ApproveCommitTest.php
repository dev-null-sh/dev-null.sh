<?php

declare(strict_types=1);

use App\Actions\ApproveCommit;
use App\Actions\RejectCommit;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Poll;

it('starts the clock of a poll once its commit is approved', function (): void {
    $commit = Commit::factory()->pending()->create();
    $poll = Poll::factory()->for($commit)->unopened()->create();

    $this->travel(2)->hours();

    resolve(ApproveCommit::class)->handle($commit);

    expect($poll->refresh()->opened_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('keeps the clock of a poll running when its commit is approved again', function (): void {
    $commit = Commit::factory()->pending()->create();
    $poll = Poll::factory()->for($commit)->unopened()->create();

    resolve(ApproveCommit::class)->handle($commit);
    $openedAt = $poll->refresh()->opened_at?->toIso8601String();

    $this->travel(1)->hour();
    resolve(RejectCommit::class)->handle($commit);
    resolve(ApproveCommit::class)->handle($commit);

    expect($poll->refresh()->opened_at?->toIso8601String())->toBe($openedAt);
});

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
