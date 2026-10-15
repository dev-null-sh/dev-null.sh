<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

final readonly class NotificationGroupsQuery
{
    /** @return CursorPaginator<int, Notification> */
    public function get(User $user, string $type = 'all'): CursorPaginator
    {
        return $this->events($user, $type)
            ->where('group_rank', 1)
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
        $visible = Notification::query()->where('recipient_id', $user->id)->relevant()->select('notifications.*');
        $filtered = Notification::query()->from('visible')->select('visible.*', 'identified.group_id')
            ->join('identified', 'identified.id', '=', 'visible.id')
            ->when($type !== 'all', fn (NotificationBuilder $query): NotificationBuilder => $query->whereIn('type', match ($type) {
                'replies' => ['replied'],
                'mentions' => ['mentioned'],
                'follows' => ['followed'],
                'moderation' => ['approved', 'rejected'],
                default => [],
            }));

        $gap = match (DB::connection($visible->getModel()->getConnectionName())->getDriverName()) {
            'mysql', 'mariadb' => 'TIMESTAMPDIFF(SECOND, previous_match_at, created_at)',
            'pgsql' => 'EXTRACT(EPOCH FROM (created_at - previous_match_at))',
            default => 'unixepoch(created_at) - unixepoch(previous_match_at)',
        };

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
            topics AS (
                SELECT visible.*,
                    CASE WHEN visible.type = 'followed' THEN visible.recipient_id
                        ELSE COALESCE(ancestry.id, visible.id) END AS topic_id
                FROM visible LEFT JOIN ancestry ON ancestry.notification_id = visible.id AND ancestry.parent_id IS NULL
            ),
            predecessors AS (
                SELECT id, topic_id, created_at,
                    LAG(created_at) OVER (PARTITION BY topic_id ORDER BY created_at, id) AS previous_match_at,
                    LAG(topic_id) OVER (ORDER BY created_at, id) AS previous_topic_id
                FROM topics
            ),
            sessions AS (
                SELECT id, topic_id, created_at,
                    SUM(CASE WHEN previous_match_at IS NULL
                        OR ({$gap} > 3600 AND previous_topic_id <> topic_id)
                        THEN 1 ELSE 0 END)
                        OVER (PARTITION BY topic_id ORDER BY created_at, id ROWS UNBOUNDED PRECEDING) AS session_number
                FROM predecessors
            ),
            identified AS (
                SELECT id,
                    FIRST_VALUE(id) OVER (PARTITION BY topic_id, session_number ORDER BY created_at, id) AS group_id
                FROM sessions
            ),
            filtered AS (
                {$filtered->toSql()}
            )
            SELECT filtered.*,
                ROW_NUMBER() OVER (PARTITION BY group_id ORDER BY created_at DESC, id DESC) AS group_rank,
                COUNT(*) OVER (PARTITION BY group_id) AS event_count
            FROM filtered
            SQL;

        return Notification::query()->fromSub($sql, 'notifications')->addBinding($visible->getBindings(), 'from')
            ->addBinding($filtered->getBindings(), 'from')
            ->withCasts(['event_count' => 'integer']);
    }
}
