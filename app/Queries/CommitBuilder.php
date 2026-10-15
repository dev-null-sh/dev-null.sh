<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

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
            ->where('status', CommitStatus::Approved)
            ->orWhere('user_id', $user->id));
    }

    public function pending(): self
    {
        return $this->where('status', CommitStatus::Pending);
    }

    /**
     * Eager-load each author with follower counts and whether the viewer follows them.
     */
    public function withAuthorFor(User $viewer): self
    {
        return $this->with([
            'user' => function (Relation $relation) use ($viewer): void {
                $relation->getQuery()->withCount(['followers', 'following'])->withExists([
                    'followers as is_following' => fn (Builder $followers): Builder => $followers->whereKey($viewer->id),
                ]);
            },
        ]);
    }
}
