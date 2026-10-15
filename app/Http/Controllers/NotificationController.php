<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MarkNotificationsAsRead;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    /**
     * Each page is marked as read once it has been sent, so it still renders as unread.
     */
    public function index(#[CurrentUser] User $user, MarkNotificationsAsRead $action): Response
    {
        return Inertia::render('notification/index', [
            'notifications' => Inertia::scroll(function () use ($user, $action): CursorPaginator {
                $notifications = $user->notifications()
                    ->relevant()
                    ->with(['actor', 'commit'])
                    ->latest()
                    ->latest('id')
                    ->cursorPaginate(20);

                /** @var array<int, Notification> $items */
                $items = $notifications->items();

                $action->handle(...$items);

                return $notifications;
            }),
        ]);
    }
}
