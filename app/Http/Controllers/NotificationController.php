<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListNotificationsRequest;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\CursorPaginator as Paginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    public function index(ListNotificationsRequest $request, #[CurrentUser] User $user, NotificationGroupsQuery $query): Response
    {
        $type = $request->type($user);

        return Inertia::render('notification/index', [
            'notifications' => Inertia::scroll(function () use ($request, $user, $type, $query): CursorPaginator {
                /** @var Paginator<int, Notification> $notifications */
                $notifications = $query->withStarredCommitImages($query->withQuotedCommits($user, $query->paginate($user, $type, $request->through())));

                $stars = $query->commitStars($user, $notifications->items());

                // Replies, mentions and quotes can be starred from the inbox, so they say how starred they are.
                return $notifications->through(fn (Notification $notification): array => $this->withCommitStars($notification, $stars));
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
    private function withCommitStars(Notification $notification, array $stars): array
    {
        $commit = $notification->commit;

        if ($commit === null || ! isset($stars[$commit->id])) {
            return $notification->toArray();
        }

        return [...$notification->toArray(), 'commit' => [...$commit->toArray(), ...$stars[$commit->id]]];
    }
}
