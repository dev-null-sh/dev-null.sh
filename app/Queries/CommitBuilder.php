<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Cursor;

/**
 * @extends Builder<Commit>
 */
final class CommitBuilder extends Builder
{
    /**
     * Approved commits are visible to everyone, guests included, while authors may also see their own pending or rejected commits.
     * A block hides each one's commits from the other.
     */
    public function visibleTo(?User $user): self
    {
        if (! $user instanceof User) {
            return $this->where($this->qualifyColumn('status'), CommitStatus::Approved);
        }

        return $this->where(fn (Builder $query): Builder => $query
            ->where($this->qualifyColumn('status'), CommitStatus::Approved)
            ->orWhere($this->qualifyColumn('user_id'), $user->id))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull($this->qualifyColumn('user_id'))
                ->orWhereNotIn($this->qualifyColumn('user_id'), $user->idsAcrossBlocks()));
    }

    /**
     * Muting only quiets what the user comes across: the commits stay reachable from their author's profile and link.
     */
    public function withoutMutedAuthorsFor(?User $user): self
    {
        return $user instanceof User ? $this->where(fn (Builder $query): Builder => $query
            ->whereNull($this->qualifyColumn('user_id'))
            ->orWhereNotIn($this->qualifyColumn('user_id'), $user->mutedIds())) : $this;
    }

    /**
     * Deleted commits stay in a thread as tombstones while they still have replies the given user can see.
     * Replies by muted people are left out along with the replies under them.
     */
    public function visibleInThreadTo(?User $user): self
    {
        return $this->withTrashed()
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->where(fn (self $query): self => $query
                ->whereNull($query->getModel()->getQualifiedDeletedAtColumn())
                ->orWhereHas('replies', fn (self $replies): self => $replies->withTrashed()->visibleTo($user)->withoutMutedAuthorsFor($user))
                ->orWhereHas('quotes', fn (self $quotes): self => $quotes->withTrashed()->visibleTo($user)->withoutMutedAuthorsFor($user)));
    }

    public function topLevel(): self
    {
        return $this->whereNull('parent_id');
    }

    /**
     * Commits created after the given one, including those created in the same second with a later, time-ordered id.
     */
    public function newerThan(Commit $commit): self
    {
        return $this->where(fn (self $query): self => $query
            ->where($this->qualifyColumn('created_at'), '>', $commit->created_at)
            ->orWhere(fn (self $query): self => $query
                ->where($this->qualifyColumn('created_at'), $commit->created_at)
                ->where($this->qualifyColumn('id'), '>', $commit->id)));
    }

    public function newestFirst(): self
    {
        return $this->reorder()->latest()->latest('id');
    }

    /**
     * Counts only the replies the given user is allowed to see.
     */
    public function withVisibleReplyCount(?User $user): self
    {
        $replies = (new Commit)->setTable('thread_replies')->newQuery()
            ->from((new Commit)->getTable().' as thread_replies')
            ->selectRaw('count(*)')
            ->where(fn (self $query): self => $query
                ->whereColumn('thread_replies.parent_id', $this->qualifyColumn('id'))
                ->orWhereColumn('thread_replies.quoted_commit_id', $this->qualifyColumn('id')))
            ->visibleInThreadTo($user)
            ->exceptSeriesContinuations();

        return $this->addSelect([$this->qualifyColumn('*')])->selectSub($replies, 'replies_count');
    }

    public function withReplyPreviews(?User $user): self
    {
        $preview = fn (HasMany $relation): HasMany => $this->configureReplyPreview($relation, $user);

        return $this->with(['replies' => $preview, 'quotes' => $preview])
            ->afterQuery($this->attachReplyPreviews(...));
    }

    /**
     * Loads the quoted commit only when the given user may see it, and as a tombstone once deleted.
     */
    public function withQuotedCommitFor(?User $viewer): self
    {
        return $this->with([
            'quotedCommit' => fn (BelongsTo $query): BelongsTo => $query
                ->withTrashed()
                ->where(fn (self $query): self => $query->visibleTo($viewer))
                ->with('user')
                ->withPollFor($viewer),
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
            ->withVisibleReplyCount(null)
            ->withCount('stars')
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

    /**
     * Loads the poll with each option's vote count and whether the viewer chose it, in two queries for any number of commits.
     */
    public function withPollFor(?User $viewer): self
    {
        return $this
            ->with(['poll.options' => fn (HasMany $options): HasMany => $options
                ->withCount('votes')
                ->withExists(['votes as voted' => fn (Builder $query): Builder => $viewer instanceof User
                    ? $query->where('user_id', $viewer->id)
                    : $query->whereRaw('1 = 0')])])
            ->afterQuery(function (Collection $commits) use ($viewer): void {
                foreach ($commits->whereInstanceOf(Commit::class) as $commit) {
                    if ($commit->relationLoaded('poll')) {
                        $commit->poll?->forceFill(['is_own' => $commit->user_id === $viewer?->id]);
                    }
                }
            });
    }

    /**
     * Everything a commit card shows for the given viewer, so every list renders its commits the same way.
     * The reply count is left out, as its presence is what gives a card its thread actions.
     */
    public function withCardDataFor(?User $viewer): self
    {
        return $this
            ->with(['user', 'linkPreview', 'images'])
            ->withQuotedCommitFor($viewer)
            ->withSavedState($viewer)
            ->withStarsFor($viewer)
            ->withPollFor($viewer);
    }

    /**
     * @param  HasMany<Commit, Commit>  $relation
     * @return HasMany<Commit, Commit>
     */
    private function configureReplyPreview(HasMany $relation, ?User $user): HasMany
    {
        return $relation
            ->visibleInThreadTo($user)
            ->exceptSeriesContinuations()
            ->withVisibleReplyCount($user)
            ->withCardDataFor($user)
            ->without('quotedCommit')
            ->newestFirst()
            ->limit(1);
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @return Collection<int, Commit>
     */
    private function attachReplyPreviews(Collection $commits): Collection
    {
        foreach ($commits as $commit) {
            if (! $commit->relationLoaded('replies') || ! $commit->relationLoaded('quotes')) {
                continue;
            }

            $replies = $commit->replies->merge($commit->quotes)
                ->sortByDesc(fn (Commit $reply): string => $reply->created_at->format('Y-m-d H:i:s.u').$reply->id)
                ->take(1)
                ->values();
            $last = $replies->last();
            $cursor = $last instanceof Commit && $commit->replies_count > $replies->count()
                ? new Cursor(['created_at' => $last->getRawOriginal('created_at'), 'id' => $last->id])->encode()
                : null;

            $commit->setAttribute('reply_preview', [
                'data' => $replies->map(fn (Commit $reply): array => $reply->toArray())->all(),
                'next_cursor' => $cursor,
                'prev_cursor' => null,
            ]);
            $commit->unsetRelation('replies')->unsetRelation('quotes');
        }

        return $commits;
    }
}
