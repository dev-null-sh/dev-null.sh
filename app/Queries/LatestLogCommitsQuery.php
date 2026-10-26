<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

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
     * @param  list<string>  $pending
     * @return list<string>
     */
    public function missing(Log $log, ?User $user, array $pending): array
    {
        if ($pending === [] || $log->isRanked()) {
            return [];
        }

        $visible = $log->query()->builder($user)->whereKey($pending)->toBase()->select('commits.id')->reorder()->pluck('commits.id');

        return array_values(collect($pending)->reject(fn (string $id): bool => $visible->contains($id))->all());
    }
}
