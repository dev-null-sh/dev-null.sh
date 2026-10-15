<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @extends Builder<Commit>
 */
final class CommitBuilder extends Builder
{
    /**
     * Approved commits are visible to everyone, while authors may also see their own pending or rejected commits.
     */
    public function visibleTo(User $user): self
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->where($this->qualifyColumn('status'), CommitStatus::Approved)
            ->orWhere($this->qualifyColumn('user_id'), $user->id));
    }

    /**
     * Deleted commits stay in a thread as tombstones while they still have replies the given user can see.
     */
    public function visibleInThreadTo(User $user): self
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

    /**
     * Loads the poll with each option's vote count and whether the viewer chose it, in two queries for any number of commits.
     */
    public function withPollFor(User $viewer): self
    {
        return $this
            ->with(['poll.options' => fn (HasMany $options): HasMany => $options
                ->withCount('votes')
                ->withExists(['votes as voted' => fn (Builder $query): Builder => $query->where('user_id', $viewer->id)])])
            ->afterQuery(function (Collection $commits) use ($viewer): void {
                foreach ($commits->whereInstanceOf(Commit::class) as $commit) {
                    $commit->poll?->forceFill(['is_own' => $commit->user_id === $viewer->id]);
                }
            });
    }
}
