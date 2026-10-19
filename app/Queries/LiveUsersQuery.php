<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class LiveUsersQuery
{
    /**
     * Everyone streaming right now, the stream with the most time left first, without the people the viewer muted or is
     * blocked from. Like `User::liveStream()`, a stream that ran out is left out even before the scheduler clears it.
     *
     * @return CursorPaginator<int, User>
     */
    public function paginate(?User $viewer, int $perPage = 30): CursorPaginator
    {
        return User::query()
            ->whereNotNull('live_platform')
            ->where('live_ends_at', '>', now())
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query
                ->whereNotIn('id', $viewer->mutedIds())
                ->whereNotIn('id', $viewer->idsAcrossBlocks()))
            ->latest('live_ends_at')
            ->latest('id')
            ->cursorPaginate($perPage);
    }
}
