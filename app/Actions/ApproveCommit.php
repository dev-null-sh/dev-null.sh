<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;

final readonly class ApproveCommit
{
    public function __construct(private ModerateCommits $moderateCommits)
    {
        //
    }

    public function handle(Commit $commit): void
    {
        $this->moderateCommits->handle([$commit->id], CommitStatus::Approved);
    }
}
