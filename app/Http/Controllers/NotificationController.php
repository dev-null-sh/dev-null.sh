<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListNotificationsRequest;
use App\Http\Resources\ShownNotificationResource;
use App\Http\Resources\ShownStreams;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    public function index(ListNotificationsRequest $request, #[CurrentUser] User $user, NotificationGroupsQuery $query): Response
    {
        $type = $request->type($user);

        return Inertia::render('notification/index', [
            'notifications' => Inertia::scroll(function () use ($request, $user, $type, $query): CursorPaginator {
                $notifications = $query->withStarredCommitImages($query->withQuotedCommits($user, $query->paginate($user, $type, $request->through())));

                $stars = $query->commitStars($user, $notifications->items());

                // Replies, mentions and quotes can be starred from the inbox, so they say how starred they are.
                return ShownStreams::notifications($notifications)->through(fn (ShownNotificationResource $shown): array => $this->withCommitStars($shown, $stars));
            })->matchOn('data.group_id'),
            'filters' => ['type' => $type],
            'readAt' => CarbonImmutable::now()->startOfSecond()->toJSON(),
        ]);
    }

    /**
     * The notification as the page gets it, its commit saying how starred it is.
     *
     * @param  array<string, array{stars_count: int, starred: bool}>  $stars
     * @return array<mixed>
     */
    private function withCommitStars(ShownNotificationResource $shown, array $stars): array
    {
        $notification = $shown->resource;
        assert($notification instanceof Notification);
        $commit = $notification->commit;

        if ($commit === null || ! isset($stars[$commit->id])) {
            return $shown->toArray();
        }

        return [...$shown->toArray(), 'commit' => [...$commit->toArray(), ...$stars[$commit->id]]];
    }
}
