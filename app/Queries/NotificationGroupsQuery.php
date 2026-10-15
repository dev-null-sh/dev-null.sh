<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class NotificationGroupsQuery
{
    /** @return CursorPaginator<int, Notification> */
    public function get(User $user, string $type = 'all', bool $unread = false): CursorPaginator
    {
        return $this->events($user, $type)
            ->where('group_rank', 1)
            ->when($unread, fn (NotificationBuilder $query): NotificationBuilder => $query->where('unread_count', '>', 0))
            ->with(['actor', 'commit'])
            ->latest()->latest('id')
            ->cursorPaginate(20)->withQueryString();
    }

    public function members(User $user, Notification $notification, string $type = 'all'): NotificationBuilder
    {
        $group = $this->events($user)->whereKey($notification->id)->sole();

        return $this->events($user, $type)->where('group_id', $group->group_id);
    }

    public function events(User $user, string $type = 'all'): NotificationBuilder
    {
        $visible = Notification::query()->where('recipient_id', $user->id)->relevant()->select('notifications.*')
            ->when($type !== 'all', fn (NotificationBuilder $query): NotificationBuilder => $query->whereIn('type', match ($type) {
                'replies' => ['replied'],
                'mentions' => ['mentioned'],
                'follows' => ['followed'],
                'moderation' => ['approved', 'rejected'],
                default => [],
            }));

        // Deleted ancestors supply grouping identity only; content comes from relevant notifications.
        $sql = <<<SQL
            WITH RECURSIVE visible AS ({$visible->toSql()}),
            ancestry AS (
                SELECT visible.id AS notification_id, commits.id, commits.parent_id
                FROM visible JOIN commits ON commits.id = visible.commit_id
                WHERE visible.type IN ('replied', 'mentioned')
                UNION ALL
                SELECT ancestry.notification_id, parent.id, parent.parent_id
                FROM ancestry JOIN commits AS parent ON parent.id = ancestry.parent_id
            ),
            grouped AS (
                SELECT visible.*, COALESCE(ancestry.id, visible.id) AS group_id
                FROM visible LEFT JOIN ancestry ON ancestry.notification_id = visible.id AND ancestry.parent_id IS NULL
            )
            SELECT grouped.*,
                ROW_NUMBER() OVER (PARTITION BY group_id ORDER BY created_at DESC, id DESC) AS group_rank,
                COUNT(*) OVER (PARTITION BY group_id) AS event_count,
                SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) OVER (PARTITION BY group_id) AS unread_count
            FROM grouped
            SQL;

        return Notification::query()->fromSub($sql, 'notifications')->addBinding($visible->getBindings(), 'from')
            ->withCasts(['event_count' => 'integer', 'unread_count' => 'integer']);
    }

    /** @return list<Notification> */
    public function snapshot(User $user, Notification $notification, string $type): array
    {
        return array_values($this->members($user, $notification, $type)
            ->where(fn (NotificationBuilder $query): NotificationBuilder => $query
                ->where('created_at', '<', $notification->created_at)
                ->orWhere(fn (NotificationBuilder $query): NotificationBuilder => $query
                    ->where('created_at', $notification->created_at)->where('id', '<=', $notification->id)))
            ->get()->all());
    }
}
