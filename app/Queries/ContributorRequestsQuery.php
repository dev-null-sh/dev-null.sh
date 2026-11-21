<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ContributorHelp;
use App\Models\ContributorRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class ContributorRequestsQuery
{
    /** @return Builder<ContributorRequest> */
    public function builder(?User $viewer, ?ContributorHelp $kind = null, string $status = 'open'): Builder
    {
        return ContributorRequest::query()
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer)->withoutMutedAuthorsFor($viewer))
            ->when($kind, fn (Builder $query): Builder => $query->where('kind', $kind))
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('status', $status))
            ->with(['commit' => function (BelongsTo $relation) use ($viewer): void {
                $relation->getQuery()->withCardDataFor($viewer)->withVisibleReplyCount($viewer);
            }])
            ->latest()->latest('id');
    }
}
