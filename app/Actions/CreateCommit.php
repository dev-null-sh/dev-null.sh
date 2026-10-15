<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Jobs\AssignCommitTopics;
use App\Models\Commit;
use App\Models\User;

final readonly class CreateCommit
{
    public function handle(User $user, string $title, ?string $description = null): Commit
    {
        $commit = $user->commits()->create([
            'title' => $title,
            'description' => $description,
            'status' => $user->isAdmin() ? CommitStatus::Approved : CommitStatus::Pending,
        ]);

        // Queued so committing never waits on, or fails because of, the Claude call.
        dispatch(new AssignCommitTopics($commit));

        return $commit;
    }
}
