<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;

final readonly class CommitThreadQuery
{
    /**
     * How many replies the viewer sees under the commit, and which reply continues the author's thread, so a client
     * that just replied can match what the commit's next render will show.
     */
    public function get(Commit $commit, ?User $viewer): Commit
    {
        return Commit::query()
            ->withVisibleReplyCount($viewer)
            ->withContinuationId($viewer)
            ->findOrFail($commit->id);
    }
}
