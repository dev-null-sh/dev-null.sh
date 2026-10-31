<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class ReportedCommitsQuery
{
    /** @return CursorPaginator<int, CommitReport> */
    public function paginate(User $viewer): CursorPaginator
    {
        return CommitReport::query()
            ->select(['id', 'commit_id', 'reason', 'details', 'images', 'created_at'])
            ->whereNull('dismissed_at')
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->approved()->visibleTo($viewer))
            ->with(['commit' => fn (BelongsTo $query): BelongsTo => $query
                ->withCardDataFor($viewer)
                ->with(['parent' => fn (BelongsTo $parent): BelongsTo => $parent
                    ->where('status', CommitStatus::Approved)
                    ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer))
                    ->with('user')])])
            ->oldest()->oldest('id')
            ->cursorPaginate(20);
    }
}
