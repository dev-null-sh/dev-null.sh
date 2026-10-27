<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class UserCommitsQuery
{
    /**
     * The commits on the Commits, Shipped, or Replies tab of a user's profile, as the viewer, or a guest, may see
     * them. The Commits tab leaves out the pinned commit, which shows above it. Shipped lists top-level commits
     * marked shipped, including one that is pinned. The Replies tab comes with the parents the viewer may see.
     */
    public function builder(User $user, ?User $viewer, bool $replies = false, bool $shipped = false): CommitBuilder
    {
        return Commit::query()
            ->whereBelongsTo($user)
            ->visibleTo($viewer)
            ->when(
                $replies,
                fn (CommitBuilder $query): CommitBuilder => $query
                    ->whereNotNull('parent_id')
                    ->with(['parent' => fn (BelongsTo $query): BelongsTo => $query
                        ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer))
                        ->with('user')]),
                fn (CommitBuilder $query): CommitBuilder => $query->topLevel()
                    ->when($shipped, fn (CommitBuilder $query): CommitBuilder => $query->where('shipped', true))
                    ->when(
                        $user->pinned_commit_id,
                        fn (CommitBuilder $query, string $id): CommitBuilder => $shipped ? $query : $query->whereKeyNot($id),
                    ),
            )
            ->withVisibleReplyCount($viewer)
            ->withReplyPreviews($viewer)
            ->withCardDataFor($viewer)
            ->latest()
            ->latest('id');
    }
}
