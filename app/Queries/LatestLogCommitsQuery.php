<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A ranked log does not put new commits on top, so it does not offer them either: they belong to the Latest log. The
 * user's own commits are left out, so committing from another device or tab is not offered back to them as new.
 */
final readonly class LatestLogCommitsQuery
{
    public function count(Log $log, ?User $user, Commit $since, ?CarbonInterface $sinceAt = null): int
    {
        if ($log->isRanked()) {
            return 0;
        }

        return $log->query()->builder($user)
            ->newerThan($since, $sinceAt)
            ->when($user, fn (CommitBuilder $query, User $user): CommitBuilder => $query->where($query->qualifyColumn('user_id'), '!=', $user->id))
            ->count();
    }
}
