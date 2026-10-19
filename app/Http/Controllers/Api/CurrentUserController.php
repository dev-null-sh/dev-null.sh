<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteUser;
use App\Http\Resources\CurrentUserResource;
use App\Http\Requests\DeleteApiCurrentUserRequest;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

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

    /**
     * Deletes the account the way the web does, and its devices with it, so the key that signed the request stops working.
     */
    public function destroy(DeleteApiCurrentUserRequest $request, #[CurrentUser] User $user, DeleteUser $action): Response
    {
        $action->handle($user);

        return response()->noContent();
    }
}
