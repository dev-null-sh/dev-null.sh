<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

final readonly class CreateCommit
{
    public function __construct(private NotifyFollowersOfCommit $notifyFollowers)
    {
        //
    }

    public function handle(User $user, string $body): Commit
    {
        $commit = $user->commits()->create([
            'body' => $body,
            'status' => $user->isAdmin() ? CommitStatus::Approved : CommitStatus::Pending,
        ]);

        // Admin commits skip moderation, so they never go through ApproveCommit.
        if ($commit->status === CommitStatus::Approved) {
            $this->notifyFollowers->handle($commit);
        }

        return $commit;
    }
}
