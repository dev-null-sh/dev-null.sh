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
    public function relevant(): self
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->where(fn (Builder $query): Builder => $query
                ->where('type', NotificationType::Followed)
                ->whereExists(fn (QueryBuilder $follows): QueryBuilder => $follows
                    ->from('follows')
                    ->whereColumn('follows.follower_id', 'notifications.actor_id')
                    ->whereColumn('follows.followed_id', 'notifications.recipient_id')))
            ->orWhere(fn (Builder $query): Builder => $query
                ->whereIn('type', [NotificationType::Replied, NotificationType::Mentioned, NotificationType::Starred])
                ->whereHas('commit', fn (CommitBuilder $commit): CommitBuilder => $commit
                    ->where('status', CommitStatus::Approved)))
            ->orWhere(fn (Builder $query): Builder => $query
                ->whereIn('type', [NotificationType::Approved, NotificationType::Rejected])
                ->whereHas('commit', fn (CommitBuilder $commit): CommitBuilder => $commit
                    ->whereColumn('commits.user_id', 'notifications.recipient_id')
                    ->whereColumn('commits.status', 'notifications.type'))))
            ->withoutSilencedActors();
    }

    /**
     * Stars are grouped per starred commit and replies per replied commit, both kept in the subject. Anything else stands alone.
     */
    public function inGroupOf(Notification $notification): self
    {
        return $this->where('recipient_id', $notification->recipient_id)
            ->where('type', $notification->type)
            ->when(
                $notification->subject_id === null,
                fn (self $query): self => $query->whereKey($notification->id),
                fn (self $query): self => $query->where('subject_id', $notification->subject_id),
            );
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

        return $this->whereNotExists($silenced->mutes(...))->whereNotExists($silenced->blocks(...));
    }
}
