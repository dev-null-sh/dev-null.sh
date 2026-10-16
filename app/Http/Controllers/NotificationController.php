<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateNotificationsReadAt;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    /**
     * Each page is marked as read once it has been sent, so it still renders as unread.
     */
    public function index(#[CurrentUser] User $user, NotificationGroupsQuery $query, UpdateNotificationsReadAt $action): Response
    {
        return Inertia::render('notification/index', [
            'notifications' => Inertia::scroll(function () use ($user, $query, $action): CursorPaginator {
                $notifications = $query->paginate($user);

                /** @var array<int, Notification> $items */
                $items = $notifications->items();

                $action->handle(...$items);

                return $notifications;
            }),
        ]);
    }
}
