<?php

declare(strict_types=1);

use App\Actions\ApproveCommit;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Notifications\NewCommitFromFollowing;
use Illuminate\Support\Facades\Notification;

it('may approve a commit', function (): void {
    $commit = Commit::factory()->pending()->create();

    $action = resolve(ApproveCommit::class);

    $action->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('notifies followers when a commit is approved', function (): void {
    Notification::fake();

    $commit = Commit::factory()->pending()->create();
    $follower = User::factory()->create();
    $follower->following()->attach($commit->user);

    resolve(ApproveCommit::class)->handle($commit);

    Notification::assertSentTo($follower, NewCommitFromFollowing::class);
});
