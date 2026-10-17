<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class CurrentUserController
{
    /**
     * The signed-in user, with the unread count the web shows on Notifications.
     */
    public function show(#[CurrentUser] User $user, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
