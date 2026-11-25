<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserNotificationTypes;
use App\Http\Requests\UpdateApiUserNotificationTypesRequest;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserNotificationTypesController
{
    /**
     * Saves which kinds of notifications the user wants to see, as choosing them on the web's notifications page does,
     * and answers with them and the unread count, which now only counts those kinds.
     */
    public function update(UpdateApiUserNotificationTypesRequest $request, #[CurrentUser] User $user, UpdateUserNotificationTypes $action, NotificationGroupsQuery $notifications): JsonResponse
    {
        $action->handle($user, $request->string('type')->value());

        return response()->json([
            'data' => ['type' => $user->notificationTypes()],
            'unread_notifications_count' => $notifications->unreadCount($user),
        ])->header('Cache-Control', 'private, no-store');
    }
}
