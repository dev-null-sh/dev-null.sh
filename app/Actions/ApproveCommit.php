<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Jobs\AssignCommitTopics;
use App\Models\Commit;

final readonly class ApproveCommit
{
    public function handle(Commit $commit): void
    {
        $commit->update(['status' => CommitStatus::Approved]);

        // Queued so approving never waits on, or fails because of, the Claude call.
        dispatch(new AssignCommitTopics($commit));
    }
}
