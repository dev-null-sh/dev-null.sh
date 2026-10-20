<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A ranked log does not put new commits on top, so it does not offer them either: they belong to the Latest log. The
 * user's own commits are left out, so committing from another device or tab is not offered back to them as new.
 */
final readonly class LatestLogCommitsQuery
{
    public function count(Log $log, ?User $user, Commit $since): int
    {
        if ($log->isRanked()) {
            return 0;
        }

        return $log->query()->builder($user)
            ->newerThan($since)
            ->when($user, fn (CommitBuilder $query, User $user): CommitBuilder => $query->where($query->qualifyColumn('user_id'), '!=', $user->id))
            ->count();
    }

    /**
     * @return array{users: Collection<int, User>, total: int}
     */
    public function authors(Log $log, ?User $user, Commit $since): array
    {
        if ($log->isRanked()) {
            return ['users' => collect(), 'total' => 0];
        }

        $query = $log->query()->builder($user)
            ->newerThan($since)
            ->when($user, fn (CommitBuilder $query, User $user): CommitBuilder => $query->where($query->qualifyColumn('user_id'), '!=', $user->id))
            ->reorder()->toBase();
        $total = (clone $query)->distinct()->count('commits.user_id');
        $ids = $query->select('commits.user_id')->distinct()->orderBy('commits.user_id')->limit(5)->pluck('user_id');

        return ['users' => User::query()->whereIn('id', $ids)->orderBy('id')->get()->toBase(), 'total' => $total];
    }
}
