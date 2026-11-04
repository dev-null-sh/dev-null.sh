<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\CursorPaginator;

final readonly class ReportedCommitsQuery
{
    /** @return CursorPaginator<int, CommitReport> */
    public function paginate(User $viewer): CursorPaginator
    {
        return $this->open($viewer)
            ->select(['id', 'commit_id', 'reason', 'details', 'images', 'created_at'])
            ->with(['commit' => fn (BelongsTo $query): BelongsTo => $query
                ->withCardDataFor($viewer)
                ->with(['user', 'parent' => fn (BelongsTo $parent): BelongsTo => $parent
                    ->where('status', CommitStatus::Approved)
                    ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer))
                    ->with('author')])])
            ->oldest()->oldest('id')
            ->cursorPaginate(20)
            ->through(fn (CommitReport $report): CommitReport => tap($report, fn (): ?Commit => $report->commit?->withPoster()));
    }

    /**
     * The reports filed into the queue and dismissed from it since a queue last read it, for a queue that may have
     * missed being told.
     *
     * @return array{filed: list<string>, dismissed: list<string>}
     */
    public function changedSince(User $viewer, CarbonImmutable $since): array
    {
        /** @var list<string> $filed */
        $filed = $this->open($viewer)->where('created_at', '>', $since)->oldest()->oldest('id')->limit(100)->pluck('id')->all();
        /** @var list<string> $dismissed */
        $dismissed = CommitReport::query()->where('dismissed_at', '>', $since)->limit(100)->pluck('id')->all();

        return ['filed' => $filed, 'dismissed' => $dismissed];
    }

    /**
     * @return Builder<CommitReport>
     */
    private function open(User $viewer): Builder
    {
        return CommitReport::query()
            ->whereNull('dismissed_at')
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->approved()->visibleTo($viewer));
    }
}
