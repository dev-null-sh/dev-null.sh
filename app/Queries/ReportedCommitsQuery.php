<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class ReportedCommitsQuery
{
    /** @return CursorPaginator<int, CommitReport> */
    public function paginate(User $viewer): CursorPaginator
    {
        return CommitReport::query()
            ->select(['id', 'commit_id', 'reason', 'details', 'created_at'])
            ->whereNull('dismissed_at')
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->approved()->visibleTo($viewer))
            ->with(['commit' => fn (BelongsTo $query): BelongsTo => $query
                ->with(['user', 'parent' => fn (BelongsTo $parent): BelongsTo => $parent->where('status', CommitStatus::Approved)->with('user')])
                ->withCount('stars')
                ->withExists(['stars as starred' => fn (Builder $stars): Builder => $stars->whereKey($viewer->id)])])
            ->oldest()->oldest('id')
            ->cursorPaginate(20);
    }
}
