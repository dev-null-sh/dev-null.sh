<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;

final readonly class LogCommitsQuery
{
    /**
     * @return CursorPaginator<int, Commit>
     */
    public function paginate(Log $log, ?User $user, ?Commit $through = null, string $cursor = ''): CursorPaginator
    {
        $query = $log->query()->builder($user);
        if ($log->isRanked() || ! $through instanceof Commit) {
            return $query->cursorPaginate(20, cursor: $cursor);
        }

        $retained = (clone $query)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->newerThan($through)
                ->orWhereKey($through->id))
            ->get();
        $perPage = max(20, $retained->count());
        $older = $query
            ->whereRowValues(['commits.created_at', 'commits.id'], '<', [$through->created_at, $through->id])
            ->limit($perPage - $retained->count() + 1)
            ->get();

        return new CursorPaginator($retained->concat($older), $perPage, options: [
            'path' => Paginator::resolveCurrentPath(),
            'parameters' => ['created_at', 'id'],
        ]);
    }
}
