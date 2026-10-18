<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * A ranked log does not put new commits on top, so it does not offer them either: they belong to the Latest log. It
 * only shows the user's own commit on top as soon as they commit.
 */
final readonly class LatestLogCommitsQuery
{
    public function count(Log $log, ?User $user, Commit $since): int
    {
        return $log->isRanked() ? 0 : $log->query()->builder($user)->newerThan($since)->count();
    }

    /**
     * The commit the user has just committed from a ranked log.
     *
     * @return Collection<int, Commit>
     */
    public function get(Log $log, ?User $user, ?Commit $committed): Collection
    {
        return $log->isRanked() && $committed instanceof Commit
            ? $log->query()->builder($user)->whereKey($committed->id)->get()
            : new Collection();
    }
}
