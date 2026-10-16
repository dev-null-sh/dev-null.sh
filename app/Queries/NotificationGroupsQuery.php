<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class NotificationGroupsQuery
{
    public const int PER_PAGE = 20;

    private const int STACKED_ACTORS = 3;

    /**
     * Each group is represented by its newest notification, carrying how many actors and unread notifications it holds.
     * Its actor says whether the user follows them, so a new follower can be followed back from the list.
     *
     * @return CursorPaginator<int, Notification>
     */
    public function paginate(User $user): CursorPaginator
    {
        $groups = $this->ranked($user)
            ->where('group_position', 1)
            ->with([
                'actor' => fn (BelongsTo $query): BelongsTo => $query
                    ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($user->id)]),
                'commit',
            ])
            ->latest()
            ->latest('id')
            ->cursorPaginate(self::PER_PAGE);

        /** @var array<int, Notification> $items */
        $items = $groups->items();

        $this->loadStackedActors($user, $items);

        return $groups;
    }

    public function unreadCount(User $user): int
    {
        return $this->ranked($user)
            ->where('group_position', 1)
            ->where('unread_count', '>', 0)
            ->count();
    }

    /**
     * @param  array<int, Notification>  $groups
     */
    private function loadStackedActors(User $user, array $groups): void
    {
        $grouped = array_filter($groups, fn (Notification $group): bool => $group->subject_id !== null);

        $stacked = $grouped === [] ? new Collection() : $this->ranked($user)
            ->where('group_position', '<=', self::STACKED_ACTORS)
            ->where(function (NotificationBuilder $query) use ($grouped): void {
                foreach ($grouped as $group) {
                    $query->orWhere(fn (NotificationBuilder $query): NotificationBuilder => $query->inGroupOf($group));
                }
            })
            ->with('actor')
            ->orderBy('group_position')
            ->get();

        foreach ($groups as $group) {
            $actors = $stacked
                ->filter(fn (Notification $notification): bool => $notification->type === $group->type && $notification->subject_id === $group->subject_id)
                ->map(fn (Notification $notification): User => $notification->actor)
                ->values()
                ->all();

            $group->setRelation('actors', new Collection($group->subject_id === null ? [$group->actor] : $actors));
        }
    }

    /**
     * Keeps each actor's newest notification per group, numbered newest first within the group.
     */
    private function ranked(User $user): NotificationBuilder
    {
        $group = 'partition by type, coalesce(subject_id, id)';

        $notifications = $user->notifications()->getQuery()
            ->relevant()
            ->select('notifications.*')
            ->selectRaw("row_number() over ({$group}, actor_id order by created_at desc, id desc) as actor_position")
            ->selectRaw("sum(case when read_at is null then 1 else 0 end) over ({$group}) as unread_count");

        $actors = Notification::query()
            ->fromSub($notifications, 'notifications')
            ->where('actor_position', 1)
            ->select('notifications.*')
            ->selectRaw("row_number() over ({$group} order by created_at desc, id desc) as group_position")
            ->selectRaw("count(*) over ({$group}) as actors_count");

        return Notification::query()->fromSub($actors, 'notifications');
    }
}
