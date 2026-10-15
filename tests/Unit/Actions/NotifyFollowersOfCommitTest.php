<?php

declare(strict_types=1);

use App\Actions\NotifyFollowersOfCommit;
use App\Models\Commit;
use App\Models\User;
use App\Notifications\NewCommitFromFollowing;
use Illuminate\Support\Facades\Notification;

it('notifies the followers of the commit author', function (): void {
    Notification::fake();

    $commit = Commit::factory()->create();
    $follower = User::factory()->create();
    $stranger = User::factory()->create();
    $follower->following()->attach($commit->user);

    resolve(NotifyFollowersOfCommit::class)->handle($commit);

    Notification::assertSentTo($follower, NewCommitFromFollowing::class, fn (NewCommitFromFollowing $notification): bool => $notification->commit->is($commit));
    Notification::assertNotSentTo([$stranger, $commit->user], NewCommitFromFollowing::class);
});
