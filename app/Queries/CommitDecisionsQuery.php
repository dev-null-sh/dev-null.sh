<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CommitDecision;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class CommitDecisionsQuery
{
    /**
     * @return CursorPaginator<int, CommitDecision>
     */
    public function paginate(): CursorPaginator
    {
        return CommitDecision::query()
            ->with(['user', 'commit.user'])
            ->latest('id')
            ->cursorPaginate(30);
    }
}
