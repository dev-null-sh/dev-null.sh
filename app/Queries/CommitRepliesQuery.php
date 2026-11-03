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
     * Quotes are listed under the commit they quote, so they don't embed it again. Given ids, only those replies are
     * listed, the ones the viewer may no longer see left out.
     *
     * @param  list<string>|null  $ids
     * @return CursorPaginator<int, Commit>
     */
    public function paginate(Commit $commit, ?User $user, bool $newest = false, int $limit = self::PER_PAGE, ?string $except = null, ?array $ids = null): CursorPaginator
    {
        return Commit::query()
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->where('parent_id', $commit->id)
                ->orWhere('quoted_commit_id', $commit->id))
            ->when($except, fn (CommitBuilder $query, string $except): CommitBuilder => $query->whereKeyNot($except))
            ->when($ids !== null, fn (CommitBuilder $query): CommitBuilder => $query->whereKey($ids ?? []))
            ->visibleInThreadTo($user)
            ->withVisibleReplyCount($user)
            ->withContinuationId($user)
            ->withCardDataFor($user)
            ->without('quotedCommit')
            ->when($newest,
                fn (CommitBuilder $query): CommitBuilder => $query->newestFirst(),
                fn (CommitBuilder $query): CommitBuilder => $query->oldest()->oldest('id'))
            ->cursorPaginate($limit);
    }

    /**
     * How many replies and quotes the viewer sees under the commit, which stays in its thread when deleted.
     */
    public function count(Commit $commit, ?User $user): int
    {
        return Commit::withTrashed()->withVisibleReplyCount($user)->findOrFail($commit->id)->replies_count;
    }
}
