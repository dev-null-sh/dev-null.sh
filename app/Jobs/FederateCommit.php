<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\RemoteFollower;
use App\Models\User;
use App\Services\ActivityStreams;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
final class FederateCommit implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    public function handle(ActivityStreams $streams): void
    {
        $user = $this->commit->user;

        if (! $user instanceof User || ! $user->federates || $this->commit->trashed()
            || $this->commit->status !== CommitStatus::Approved || $this->commit->parent_id !== null) {
            return;
        }

        $activity = $streams->create($this->commit->load('images'));

        $user->remoteFollowers
            ->map(fn (RemoteFollower $follower): string => $follower->deliveryInbox())
            ->unique()
            ->each(fn (string $inbox): PendingDispatch => dispatch(new DeliverActivity($user, $inbox, $activity)));
    }
}
