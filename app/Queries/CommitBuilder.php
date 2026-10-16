<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @extends Builder<Commit>
 */
final class CommitBuilder extends Builder
{
    /**
     * Approved commits are visible to everyone, guests included, while authors may also see their own pending or rejected commits.
     */
    public function visibleTo(?User $user): self
    {
        if (! $user instanceof User) {
            return $this->where($this->qualifyColumn('status'), CommitStatus::Approved);
        }

        return $this->where(fn (Builder $query): Builder => $query
            ->where($this->qualifyColumn('status'), CommitStatus::Approved)
            ->orWhere($this->qualifyColumn('user_id'), $user->id));
    }

    /**
     * Deleted commits stay in a thread as tombstones while they still have replies the given user can see.
     */
    public function visibleInThreadTo(?User $user): self
    {
        return $this->withTrashed()
            ->visibleTo($user)
            ->where(fn (self $query): self => $query
                ->whereNull($query->getModel()->getQualifiedDeletedAtColumn())
                ->orWhereHas('replies', fn (self $replies): self => $replies->withTrashed()->visibleTo($user)));
    }

    public function topLevel(): self
    {
        return $this->whereNull('parent_id');
    }

    /**
     * Counts only the replies the given user is allowed to see.
     */
    public function withVisibleReplyCount(?User $user): self
    {
        return $this->withCount([
            'replies' => fn (self $query): self => $query->visibleInThreadTo($user)->exceptSeriesContinuations(),
        ]);
    }

    /**
     * Later parts of a series are replies to the part before them, yet they read as part of it rather than as replies.
     */
    public function exceptSeriesContinuations(): self
    {
        return $this->where(fn (self $query): self => $query
            ->whereNull($this->qualifyColumn('series_position'))
            ->orWhere($this->qualifyColumn('series_position'), 1));
    }

    /**
     * A series is moderated as a whole, so the queue lists it once: under its first pending part.
     */
    public function exceptLaterPendingSeriesParts(): self
    {
        return $this->whereNotExists(fn (QueryBuilder $query): QueryBuilder => $query
            ->from($this->getModel()->getTable(), 'earlier')
            ->whereColumn('earlier.series_id', $this->qualifyColumn('series_id'))
            ->whereColumn('earlier.series_position', '<', $this->qualifyColumn('series_position'))
            ->where('earlier.status', CommitStatus::Pending)
            ->whereNull('earlier.deleted_at'));
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
            ->withCount(['replies' => fn (self $query): self => $query->approved()->exceptSeriesContinuations(), 'stars'])
            ->with(['user', 'linkPreview', 'images', 'parent' => fn (BelongsTo $query): BelongsTo => $query->where('status', CommitStatus::Approved)]);
    }

    /**
     * Guests have nothing saved, yet still get the state, so they see the same save button that asks them to sign in.
     */
    public function withSavedState(?User $user): self
    {
        return $this->withExists([
            'bookmarks as is_saved' => fn (Builder $query): Builder => $user instanceof User
                ? $query->where('user_id', $user->id)
                : $query->whereRaw('1 = 0'),
        ]);
    }

    public function pending(): self
    {
        return $this->where('status', CommitStatus::Pending);
    }

    /**
     * Guests see how many stars a commit has, without having starred any.
     */
    public function withStarsFor(?User $viewer): self
    {
        return $this
            ->withCount('stars')
            ->withExists([
                'stars as starred' => fn (Builder $query): Builder => $viewer instanceof User
                    ? $query->whereKey($viewer->id)
                    : $query->whereRaw('1 = 0'),
            ]);
    }
}
