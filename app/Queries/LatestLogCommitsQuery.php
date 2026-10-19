<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A ranked log does not put new commits on top, so it does not offer them either: they belong to the Latest log.
 */
final readonly class LatestLogCommitsQuery
{
    public function count(Log $log, ?User $user, Commit $since, ?CarbonInterface $sinceAt = null): int
    {
        return $log->isRanked() ? 0 : $log->query()->builder($user)->newerThan($since, $sinceAt)->count();
    }
}
