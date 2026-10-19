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
    /**
     * Moderators review reported commits in repos too, though they aren't contributors: they see the commit and its
     * parent here, but the repo stays closed to them.
     *
     * @return CursorPaginator<int, CommitReport>
     */
    public function paginate(User $viewer): CursorPaginator
    {
        return CommitReport::query()
            ->select(['id', 'commit_id', 'reason', 'details', 'created_at'])
            ->whereNull('dismissed_at')
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->approved()->withoutBlocksAcross($viewer))
            ->with(['commit' => fn (BelongsTo $query): BelongsTo => $query
                ->withCardDataFor($viewer)
                ->with(['parent' => fn (BelongsTo $parent): BelongsTo => $parent
                    ->where('status', CommitStatus::Approved)
                    ->where(fn (CommitBuilder $query): CommitBuilder => $query->withoutBlocksAcross($viewer))
                    ->with('user')])])
            ->oldest()->oldest('id')
            ->cursorPaginate(20);
    }
}
