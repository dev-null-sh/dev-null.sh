<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * A ranked log does not put new commits on top, so they are offered above it instead. The user's own commits are left
 * out: the log shows them on top as soon as they commit.
 */
final readonly class LatestLogCommitsQuery
{
    public const int LIMIT = 50;

    /**
     * The newest commit when the log was loaded, from which newer commits are counted and offered.
     */
    public function newestId(Log $log, ?User $user): ?string
    {
        return $log->query()->builder($user)
            ->setEagerLoads([])
            ->select('commits.id')
            ->newestFirst()
            ->first()
            ?->id;
    }

    public function count(Log $log, ?User $user, Commit $since): int
    {
        return $this->newerThan($log, $user, $since)->count();
    }

    /**
     * The commits newer than the given one, the oldest of them first so the ones left over are offered next time, or
     * else the commit the user has just committed.
     *
     * @return Collection<int, Commit>
     */
    public function get(Log $log, ?User $user, ?Commit $since, ?Commit $committed): Collection
    {
        if (! $log->isRanked()) {
            return new Collection();
        }

        if ($since instanceof Commit) {
            return $this->newerThan($log, $user, $since)
                ->reorder()
                ->oldest()
                ->oldest('id')
                ->limit(self::LIMIT)
                ->get()
                ->reverse()
                ->values();
        }

        return $committed instanceof Commit
            ? $log->query()->builder($user)->whereKey($committed->id)->get()
            : new Collection();
    }

    private function newerThan(Log $log, ?User $user, Commit $since): CommitBuilder
    {
        return $log->query()->builder($user)
            ->newerThan($since)
            ->when($log->isRanked() ? $user : null, fn (CommitBuilder $query, User $user): CommitBuilder => $query->whereNot('commits.user_id', $user->id));
    }
}
