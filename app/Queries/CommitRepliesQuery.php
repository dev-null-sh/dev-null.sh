<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class CommitRepliesQuery
{
    public const int PER_PAGE = 50;

    /**
     * @return CursorPaginator<int, Commit>
     */
    public function paginate(Commit $commit, User $user): CursorPaginator
    {
        return $commit->replies()
            ->visibleInThreadTo($user)
            ->withVisibleReplyCount($user)
            ->with(['user', 'linkPreview'])
            ->oldest()
            ->oldest('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
