<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator as Paginator;
use Illuminate\Support\Facades\DB;

final readonly class NotificationGroupsQuery
{
    public const int PER_PAGE = 20;

    private const int STACKED_ACTORS = 3;

    private const int WINDOW_ROWS_PER_GROUP = 32;

    private const int WINDOW_ATTEMPTS = 4;

    private const int MIN_WINDOW_GROWTH = 2;

    private const int MAX_WINDOW_GROWTH = 8;

    /** @return CursorPaginator<int, Notification> */
    public function paginate(User $user, string $type = 'all', ?CarbonImmutable $through = null): CursorPaginator
    {
        $perPage = $through instanceof CarbonImmutable
            ? max(self::PER_PAGE, $this->groupsSince($user, $through, $type)->where('group_rank', 1)->count())
            : self::PER_PAGE;

        $groups = $this->groups($user, $type, $perPage, Paginator::resolveCurrentCursor())->appends(request()->except('through'));

        $this->loadStackedActors($groups->items());

        return $groups;
    }

    /**
     * The inbox shows a quote with the commit it quotes, which is always the recipient's own, so only the recipient's
     * commits load there: a reply that quotes someone else never brings along a commit the recipient may not see.
     *
     * @param  CursorPaginator<int, Notification>  $groups
     * @return CursorPaginator<int, Notification>
     */
    public function withQuotedCommits(User $user, CursorPaginator $groups): CursorPaginator
    {
        new Collection($groups->items())->loadMissing([
            'commit.quotedCommit' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->whereBelongsTo($user),
        ]);

        return $groups;
    }

    /**
     * A star is always on the recipient's own commit, so its images may show beside it: an image-only commit has nothing
     * else to show.
     *
     * @param  CursorPaginator<int, Notification>  $groups
     * @return CursorPaginator<int, Notification>
     */
    public function withStarredCommitImages(CursorPaginator $groups): CursorPaginator
    {
        new Collection($groups->items())->where('type', NotificationType::Starred)->loadMissing('commit.images');

        return $groups;
    }

    /**
     * Whether two unread notifications share a group only depends on what happened between them, so grouping from the
     * oldest unread one onward counts the same groups as grouping the whole history, which every page would pay for.
     */
    public function unreadCount(User $user, string $type = 'all'): int
    {
        if ($type === 'none') {
            return 0;
        }

        $oldestUnread = $this->visible($user)->unread()->oldest()->first();

        return $oldestUnread instanceof Notification
            ? $this->groupsSince($user, $oldestUnread->created_at, $type)->where('group_rank', 1)->where('unread_count', '>', 0)->count()
            : 0;
    }

    public function members(User $user, Notification $notification, string $type = 'all'): NotificationBuilder
    {
        return $this->group($this->visible($user), $type, $notification)
            ->with(['actor' => fn (BelongsTo $query): BelongsTo => $query
                ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($user->id)])]);
    }

    public function events(User $user, string $type = 'all'): NotificationBuilder
    {
        return $this->group($this->visible($user), $type);
    }

    /**
     * Grouping the whole history costs time in proportion to its size for a page of groups, so the page is grouped
     * from a window of recent notifications, widened until it provably holds the same groups as the whole history would.
     *
     * @return CursorPaginator<int, Notification>
     */
    private function groups(User $user, string $type, int $perPage, ?Cursor $cursor): CursorPaginator
    {
        $until = $this->windowEnd($user, $cursor);
        $rows = $perPage * self::WINDOW_ROWS_PER_GROUP;

        $sparse = $type !== 'all' && $this->below($user, $cursor)->whereIn('type', $this->kinds($type))->offset($rows - 1)->doesntExist();

        for ($round = 0; ! $sparse && $round < self::WINDOW_ATTEMPTS; $round++) {
            $since = $this->windowStart($user, $cursor, $rows);

            if (! $since instanceof CarbonInterface) {
                break;
            }

            $groups = $this->page($this->window($user, $since, $until), $user, $type, $perPage);

            if ($this->windowSuffices($groups, $since)) {
                return $groups;
            }

            $rows = $this->widened($rows, $perPage, count($groups->items()));
        }

        return $this->page($this->window($user, null, $until), $user, $type, $perPage);
    }

    /** @return CursorPaginator<int, Notification> */
    private function page(NotificationBuilder $visible, User $user, string $type, int $perPage): CursorPaginator
    {
        $order = 'PARTITION BY group_id ORDER BY CASE WHEN actor_position = 1 THEN 0 ELSE 1 END, created_at DESC, id DESC
            ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING';
        $events = $this->group($visible, $type)->select('notifications.*')
            ->selectRaw("NTH_VALUE(actor_id, 2) OVER ({$order}) AS second_actor_id")
            ->selectRaw("NTH_VALUE(actor_id, 3) OVER ({$order}) AS third_actor_id");

        return Notification::query()->fromSub($events, 'notifications')
            ->where('group_rank', 1)
            ->with([
                'actor' => fn (BelongsTo $query): BelongsTo => $query
                    ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($user->id)]),
                'commit',
            ])
            ->latest()->latest('id')
            ->cursorPaginate($perPage);
    }

    private function widened(int $rows, int $perPage, int $found): int
    {
        return (int) ceil($rows * min(self::MAX_WINDOW_GROWTH, max(self::MIN_WINDOW_GROWTH, 2 * ($perPage + 1) / max(1, $found))));
    }

    private function windowCoversHalf(NotificationBuilder $below, int $rows): bool
    {
        return $below->clone()->offset(2 * $rows - 1)->doesntExist();
    }

    private function windowStart(User $user, ?Cursor $cursor, int $rows): ?CarbonInterface
    {
        $below = $this->below($user, $cursor)->latest()->latest('id');

        if ($this->windowCoversHalf($below, $rows)) {
            return null;
        }

        return $this->below($user, $cursor)->relevant()
            ->where('created_at', '>=', $below->offset($rows - 1)->value('created_at'))
            ->oldest()->oldest('id')->first()?->created_at;
    }

    private function below(User $user, ?Cursor $cursor): NotificationBuilder
    {
        return Notification::query()->where('recipient_id', $user->id)
            ->when($cursor, fn (NotificationBuilder $query, Cursor $cursor): NotificationBuilder => $query
                ->whereRowValues(['created_at', 'id'], '<', [$cursor->parameter('created_at'), $cursor->parameter('id')]));
    }

    /**
     * A page past a cursor needs the notifications above it only to tell the groups reaching back from above the cursor
     * apart from those ending below it. Such a group skips the hour past the first notification at or above the cursor
     * only by continuing a session over a gap longer than an hour, which it cannot, as that notification came between.
     * So the window ends an hour after that notification, and a page before a cursor keeps all that lies above it.
     */
    private function windowEnd(User $user, ?Cursor $cursor): ?CarbonInterface
    {
        if (! $cursor instanceof Cursor || ! $cursor->pointsToNextItems()) {
            return null;
        }

        return $this->visible($user)
            ->whereRowValues(['created_at', 'id'], '>=', [$cursor->parameter('created_at'), $cursor->parameter('id')])
            ->oldest()->oldest('id')->first()?->created_at->addHour();
    }

    /**
     * Grouping the window splits groups like the whole history does, apart from cutting off the oldest group of each
     * topic (see groupsSince) and the groups going on past the window's end. Either bound may be left off: a window
     * without a start takes the whole history below its end. Stars never split, so a star group spans a
     * commit's whole history: taking along every star of each commit starred within the window keeps those groups
     * whole, and the extra stars cannot change how anything else groups, as a notification only looks back past its
     * own topic's previous one.
     */
    private function window(User $user, ?CarbonInterface $since, ?CarbonInterface $until): NotificationBuilder
    {
        if (! $since instanceof CarbonInterface && ! $until instanceof CarbonInterface) {
            return $this->visible($user);
        }

        $within = fn (NotificationBuilder $query): NotificationBuilder => $query
            ->when($since, fn (NotificationBuilder $query, CarbonInterface $since): NotificationBuilder => $query->where('created_at', '>=', $since))
            ->when($until, fn (NotificationBuilder $query, CarbonInterface $until): NotificationBuilder => $query->where('created_at', '<=', $until));
        $starredWithin = $within($this->visible($user)->select('commit_id')->where('type', 'starred'));

        return $within($this->visible($user))->unionAll(
            $this->visible($user)->where('type', 'starred')->whereNot($within)->whereIn('commit_id', $starredWithin)
        );
    }

    /**
     * A page from the window matches one from the whole history when the window holds more groups than the page and no
     * group on it was cut off. A session continues past its topic's previous notification only when that one is at most
     * an hour older, so a group that starts over an hour after the window began has nothing before the window: the row
     * just before its first member would have been another member. Groups starting within that first hour may go on
     * below, and the window widens until none is on the page.
     *
     * @param  CursorPaginator<int, Notification>  $groups
     */
    private function windowSuffices(CursorPaginator $groups, CarbonInterface $since): bool
    {
        $horizon = $since->toImmutable()->addHour();

        return $groups->hasMorePages() && array_all($groups->items(), fn (Notification $group): bool => $group->type === NotificationType::Starred
            || $group->group_started_at->isAfter($horizon));
    }

    /**
     * Grouping only the notifications from some point onwards puts every one of them in the same group as grouping all
     * of them does, apart from the oldest group of each topic missing what came before that point. So it finds just as
     * many groups with notifications from then on.
     */
    private function groupsSince(User $user, CarbonInterface $since, string $type = 'all'): NotificationBuilder
    {
        return $this->group($this->visible($user)->where('created_at', '>=', $since), $type);
    }

    private function visible(User $user): NotificationBuilder
    {
        return Notification::query()->where('recipient_id', $user->id)->relevant()->select('notifications.*');
    }

    /** @return list<string> */
    private function kinds(string $type): array
    {
        return array_merge(...array_map(fn (string $category): array => match ($category) {
            'replies' => ['replied'],
            'quotes' => ['quoted'],
            'stars' => ['starred'],
            'mentions' => ['mentioned'],
            'follows' => ['followed'],
            'moderation' => ['approved', 'rejected'],
            default => [],
        }, explode(',', $type)));
    }

    private function group(NotificationBuilder $visible, string $type = 'all', ?Notification $member = null): NotificationBuilder
    {
        $filtered = Notification::query()->from('visible')->select('visible.*', 'identified.group_id', 'identified.group_started_at')
            ->join('identified', 'identified.id', '=', 'visible.id')
            ->when($member, fn (NotificationBuilder $query, Notification $member): NotificationBuilder => $query->where(
                'identified.group_id',
                fn (QueryBuilder $query): QueryBuilder => $query->from('identified')->where('id', $member->id)->select('group_id'),
            ))
            ->when($type !== 'all', fn (NotificationBuilder $query): NotificationBuilder => $query->whereIn('type', $this->kinds($type)));

        $gap = match (DB::connection($visible->getModel()->getConnectionName())->getDriverName()) {
            'mysql', 'mariadb' => 'TIMESTAMPDIFF(SECOND, previous_match_at, created_at)',
            'pgsql' => 'EXTRACT(EPOCH FROM (created_at - previous_match_at))',
            default => 'unixepoch(created_at) - unixepoch(previous_match_at)',
        };

        // Deleted ancestors supply grouping identity only; content comes from relevant notifications. A commit's
        // recorded root is always one of its ancestors, so the walk up the thread only continues past it when the
        // recorded root is itself a reply. Follows never group across days, as a busy account gets followed so often
        // that its follows would otherwise never stop joining a single group.
        $sql = <<<SQL
            WITH RECURSIVE visible AS ({$visible->toSql()}),
            threads AS (
                SELECT visible.id AS notification_id, roots.id, roots.parent_id
                FROM visible JOIN commits ON commits.id = visible.commit_id
                JOIN commits AS roots ON roots.id = COALESCE(commits.root_id, commits.id)
                WHERE visible.type = 'mentioned'
            ),
            ancestry AS (
                SELECT threads.notification_id, parent.id, parent.parent_id
                FROM threads JOIN commits AS parent ON parent.id = threads.parent_id
                UNION ALL
                SELECT ancestry.notification_id, parent.id, parent.parent_id
                FROM ancestry JOIN commits AS parent ON parent.id = ancestry.parent_id
            ),
            topics AS (
                SELECT visible.*,
                    CASE WHEN visible.type = 'starred' THEN 'starred'
                        WHEN visible.type = 'replied' THEN 'replied' ELSE 'activity' END AS topic_kind,
                    CASE WHEN visible.type = 'followed' THEN visible.recipient_id
                        WHEN visible.type = 'starred' THEN visible.commit_id
                        WHEN visible.type = 'replied' THEN visible.id
                        ELSE COALESCE(ancestry.id, threads.id, visible.id) END AS topic_id,
                    CASE WHEN visible.type = 'followed' THEN DATE(visible.created_at) END AS topic_day
                FROM visible LEFT JOIN threads ON threads.notification_id = visible.id
                LEFT JOIN ancestry ON ancestry.notification_id = visible.id AND ancestry.parent_id IS NULL
            ),
            predecessors AS (
                SELECT id, topic_kind, topic_id, topic_day, created_at,
                    LAG(created_at) OVER (PARTITION BY topic_kind, topic_id, topic_day ORDER BY created_at, id) AS previous_match_at,
                    LAG(topic_kind) OVER (ORDER BY created_at, id) AS previous_topic_kind,
                    LAG(topic_id) OVER (ORDER BY created_at, id) AS previous_topic_id
                FROM topics
            ),
            sessions AS (
                SELECT id, topic_kind, topic_id, topic_day, created_at,
                    SUM(CASE WHEN topic_kind = 'starred' THEN 0
                        WHEN previous_match_at IS NULL
                        OR ({$gap} > 3600 AND (previous_topic_kind <> topic_kind OR previous_topic_id <> topic_id))
                        THEN 1 ELSE 0 END)
                        OVER (PARTITION BY topic_kind, topic_id, topic_day ORDER BY created_at, id ROWS UNBOUNDED PRECEDING) AS session_number
                FROM predecessors
            ),
            identified AS (
                SELECT id,
                    FIRST_VALUE(id) OVER (PARTITION BY topic_kind, topic_id, topic_day, session_number ORDER BY created_at, id) AS group_id,
                    FIRST_VALUE(created_at) OVER (PARTITION BY topic_kind, topic_id, topic_day, session_number ORDER BY created_at, id) AS group_started_at
                FROM sessions
            ),
            filtered AS (
                {$filtered->toSql()}
            ),
            ranked_actors AS (
                SELECT filtered.*,
                    ROW_NUMBER() OVER (PARTITION BY group_id, actor_id ORDER BY created_at DESC, id DESC) AS actor_position
                FROM filtered
            )
            SELECT ranked_actors.*,
                ROW_NUMBER() OVER (PARTITION BY group_id ORDER BY created_at DESC, id DESC) AS group_rank,
                COUNT(*) OVER (PARTITION BY group_id) AS event_count,
                SUM(CASE WHEN actor_position = 1 THEN 1 ELSE 0 END) OVER (PARTITION BY group_id) AS actors_count,
                SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) OVER (PARTITION BY group_id) AS unread_count
            FROM ranked_actors
            SQL;

        return Notification::query()->fromSub($sql, 'notifications')->addBinding($visible->getBindings(), 'from')
            ->addBinding($filtered->getBindings(), 'from')
            ->withCasts(['event_count' => 'integer']);
    }

    /** @param array<int, Notification> $groups */
    private function loadStackedActors(array $groups): void
    {
        $grouped = array_filter($groups, fn (Notification $group): bool => $group->actors_count > 1);
        $actors = $grouped === [] ? new Collection() : User::query()
            ->findMany(array_merge(...array_map($this->stackedActorIds(...), $grouped)));

        foreach ($groups as $group) {
            $groupActors = $group->actors_count === 1 ? [$group->actor] : array_filter(array_map(
                $actors->find(...),
                $this->stackedActorIds($group),
            ));

            $group->setRelation('actors', new Collection(array_values($groupActors)));
        }
    }

    /**
     * The window lists the newest notification of each actor first, so past the group's actors it repeats them.
     *
     * @return list<string>
     */
    private function stackedActorIds(Notification $group): array
    {
        return array_slice(
            array_values(array_filter([$group->actor_id, $group->second_actor_id, $group->third_actor_id])),
            0,
            min(self::STACKED_ACTORS, $group->actors_count),
        );
    }
}
