<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;

final readonly class ApproveCommits
{
    /**
     * @param  array<int, string>  $commitIds
     */
    public function handle(array $commitIds): void
    {
        Commit::query()
            ->whereKey($commitIds)
            ->update(['status' => CommitStatus::Approved]);
    }
}
