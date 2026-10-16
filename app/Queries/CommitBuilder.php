<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @extends Builder<Commit>
 */
final class CommitBuilder extends Builder
{
    /**
     * Approved commits are visible to everyone, while authors may also see their own pending or rejected commits.
     * A block hides each one's commits from the other.
     */
    public function visibleTo(User $user): self
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->where($this->qualifyColumn('status'), CommitStatus::Approved)
            ->orWhere($this->qualifyColumn('user_id'), $user->id))
            ->whereNotIn($this->qualifyColumn('user_id'), $user->idsAcrossBlocks());
    }

    /**
     * Muting only quiets what the user comes across: the commits stay reachable from their author's profile and link.
     */
    public function withoutMutedAuthorsFor(User $user): self
    {
        return $this->whereNotIn($this->qualifyColumn('user_id'), $user->mutedIds());
    }

    /**
     * Deleted commits stay in a thread as tombstones while they still have replies the given user can see.
     * Replies by muted people are left out along with the replies under them.
     */
    public function visibleInThreadTo(User $user): self
    {
        return $this->withTrashed()
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->where(fn (self $query): self => $query
                ->whereNull($query->getModel()->getQualifiedDeletedAtColumn())
                ->orWhereHas('replies', fn (self $replies): self => $replies->withTrashed()->visibleTo($user)->withoutMutedAuthorsFor($user)));
    }

    public function topLevel(): self
    {
        return $this->whereNull('parent_id');
    }

    /**
     * Counts only the replies the given user is allowed to see.
     */
    public function withVisibleReplyCount(User $user): self
    {
        return $this->withCount([
            'replies' => fn (self $query): self => $query->visibleInThreadTo($user),
        ]);
    }

    public function approved(): self
    {
        return $this->where('status', CommitStatus::Approved);
    }

    /**
     * Loads what anyone may see around the commits, like Commit::loadPublicPreview(): approved replies, stars, images, and the parent only when approved.
     */
    public function withPublicPreview(): self
    {
        return $this
            ->withCount(['replies' => fn (self $query): self => $query->approved(), 'stars'])
            ->with(['user', 'linkPreview', 'images', 'parent' => fn (BelongsTo $query): BelongsTo => $query->where('status', CommitStatus::Approved)]);
    }

    public function withSavedState(User $user): self
    {
        return $this->withExists([
            'bookmarks as is_saved' => fn (Builder $query): Builder => $query->where('user_id', $user->id),
        ]);
    }

    public function pending(): self
    {
        return $this->where('status', CommitStatus::Pending);
    }

    public function withStarsFor(User $viewer): self
    {
        return $this
            ->withCount('stars')
            ->withExists([
                'stars as starred' => fn (Builder $query): Builder => $query->whereKey($viewer->id),
            ]);
    }
}
