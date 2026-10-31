<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @extends Builder<Notification>
 */
final class NotificationBuilder extends Builder
{
    /**
     * Each exists is offset by nothing, which keeps it a lookup per notification. Without the offset, exists checks
     * joined by "or" get planned as hashes over every follow or every commit, which takes tens of milliseconds for
     * someone with thousands of notifications even when only a few are unread.
     */
    public function relevant(): self
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->where(fn (Builder $query): Builder => $query
                ->where('type', NotificationType::Followed)
                ->whereExists(fn (QueryBuilder $follows): QueryBuilder => $follows
                    ->from('follows')
                    ->whereColumn('follows.follower_id', 'notifications.actor_id')
                    ->whereColumn('follows.followed_id', 'notifications.recipient_id')
                    ->offset(0)))
            ->orWhere(fn (Builder $query): Builder => $query
                ->whereIn('type', [NotificationType::Replied, NotificationType::Quoted, NotificationType::Mentioned, NotificationType::Starred, NotificationType::FirstCommit])
                ->whereHas('commit', fn (CommitBuilder $commit): CommitBuilder => $commit
                    ->where('status', CommitStatus::Approved)
                    ->offset(0)))
            ->orWhere(fn (Builder $query): Builder => $query
                ->whereIn('type', [NotificationType::Approved, NotificationType::Rejected])
                ->whereHas('commit', fn (CommitBuilder $commit): CommitBuilder => $commit
                    ->whereColumn('commits.user_id', 'notifications.recipient_id')
                    ->whereColumn('commits.status', 'notifications.type')
                    ->offset(0)))
            ->orWhere('type', NotificationType::LeveledUp))
            ->withoutSilencedActors();
    }

    public function unread(): self
    {
        return $this->whereNull('read_at');
    }

    /**
     * Leaves out what actors the recipient mutes did, and anything from across a block. Moderation
     * notifications are their own actor, and nobody can mute or block themselves.
     */
    private function withoutSilencedActors(): self
    {
        $silenced = new SilencedActorQuery('notifications.recipient_id', 'notifications.actor_id');

        return $this->whereNotExists($silenced->mutes(...))
            ->whereNotExists($silenced->blocking(...))
            ->whereNotExists($silenced->blockers(...));
    }
}
