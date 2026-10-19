<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;

final readonly class BranchStatsQuery
{
    /**
     * @return array{commits: int, authors: int}
     */
    public function get(string $branch): array
    {
        $base = Commit::query()
            ->where('status', CommitStatus::Approved)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->onBranch($branch);

        return [
            'commits' => (clone $base)->count(),
            'authors' => (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
        ];
    }
}
