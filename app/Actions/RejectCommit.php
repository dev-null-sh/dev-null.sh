<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;

final readonly class RejectCommit
{
    public function __construct(private ModerateCommits $moderateCommits)
    {
        //
    }

    public function handle(Commit $commit, ?string $reason = null): void
    {
        $this->moderateCommits->handle([$commit->id], CommitStatus::Rejected, $reason);
    }
}
