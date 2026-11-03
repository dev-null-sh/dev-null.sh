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
    /**
     * Counts what is newer than the commit, or everything for a log that showed nothing yet.
     */
    public function count(Log $log, ?User $user, ?Commit $since): int
    {
        if ($log->isRanked()) {
            return 0;
        }

        return $log->query()->builder($user)
            ->when($since, fn (CommitBuilder $query, Commit $since): CommitBuilder => $query->newerThan($since))
            // The user's own commits aren't news to them, nor are their organizations', as the log's page tells them apart.
            ->when($user, fn (CommitBuilder $query, User $user): CommitBuilder => $query
                ->where($query->qualifyColumn('author_id'), '!=', $user->id)
                ->whereNotIn($query->qualifyColumn('author_id'), $user->organizations()->select('organizations.id')))
            ->count();
    }

    /**
     * How many commits the log holds from the oldest it loaded to the newest, both included, so a page that may have
     * missed updates can tell whether what it shows still matches: a different number means commits were removed or
     * placed in between. A ranked log isn't counted, as it is never reshaped while read.
     */
    public function window(Log $log, ?User $user, ?Commit $oldest, ?Commit $newest): ?int
    {
        if ($log->isRanked() || ! $oldest instanceof Commit || ! $newest instanceof Commit) {
            return null;
        }

        return $log->query()->builder($user)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->newerThan($oldest)->orWhere('commits.id', $oldest->id))
            ->whereNot(fn (CommitBuilder $query): CommitBuilder => $query->newerThan($newest))
            ->count();
    }

    /**
     * @param  list<string>  $pending
     * @return list<string>
     */
    public function missing(Log $log, ?User $user, array $pending): array
    {
        if ($pending === []) {
            return [];
        }

        // A ranked log is drawn once: what drops in rank stays until it is refreshed, and only what the viewer can no
        // longer see leaves it.
        $visible = ($log->isRanked() ? Commit::query()->visibleTo($user) : $log->query()->builder($user))
            ->whereKey($pending)->toBase()->select('commits.id')->reorder()->pluck('commits.id');

        return array_values(collect($pending)->reject(fn (string $id): bool => $visible->contains($id))->all());
    }

    /**
     * The commits that now belong among the ones the log shows, down to the oldest it loaded, without being newer than
     * them all: older ones approved since, or visible again. A ranked log takes none until it is refreshed.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function arrived(Log $log, ?User $user, array $ids, ?Commit $through): array
    {
        if ($ids === [] || $log->isRanked() || ! $through instanceof Commit) {
            return [];
        }

        $arrived = $log->query()->builder($user)
            ->whereKey($ids)
            ->where('commits.created_at', '>=', $through->created_at)
            ->toBase()->select('commits.id')->reorder()->pluck('commits.id');

        return array_values(array_filter($ids, fn (string $id): bool => $arrived->contains($id)));
    }
}
