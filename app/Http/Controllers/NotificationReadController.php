<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteNotificationsReadAt;
use App\Actions\UpdateNotificationsReadAt;
use App\Http\Requests\UpdateNotificationReadRequest;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class NotificationReadController
{
    public function update(UpdateNotificationReadRequest $request, #[CurrentUser] User $user, Notification $notification, NotificationGroupsQuery $query, UpdateNotificationsReadAt $read, DeleteNotificationsReadAt $unread): RedirectResponse
    {
        $notifications = $request->boolean('group')
            ? $query->snapshot($user, $notification, $request->type())
            : [$notification];

        ($request->boolean('read') ? $read : $unread)->handle(...$notifications);

        return back();
    }
}
