<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;

final readonly class LogCommitsQuery
{
    /**
     * @return CursorPaginator<int, Commit>
     */
    public function paginate(Log $log, ?User $user, ?Commit $through = null, string $cursor = '', ?CarbonInterface $throughAt = null): CursorPaginator
    {
        $query = $log->query()->builder($user);
        if ($log->isRanked() || ! $through instanceof Commit) {
            return $query->cursorPaginate(20, cursor: $cursor);
        }

        $throughAt ??= $through->approved_at ?? $through->created_at;

        $retained = (clone $query)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->newerThan($through, $throughAt)
                ->orWhereKey($through->id))
            ->get();
        $perPage = max(20, $retained->count());
        $older = $query
            ->whereRaw(
                '(coalesce(commits.approved_at, commits.created_at), commits.id) < (?, ?)',
                [$throughAt, $through->id],
            )
            ->limit($perPage - $retained->count() + 1)
            ->get();

        return new CursorPaginator($retained->concat($older), $perPage, options: [
            'path' => Paginator::resolveCurrentPath(),
            'parameters' => ['feed_at', 'id'],
        ]);
    }
}
