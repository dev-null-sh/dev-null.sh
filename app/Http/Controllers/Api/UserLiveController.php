<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteUserLive;
use App\Actions\UpdateUserLive;
use App\Http\Requests\UpdateApiUserLiveRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserLiveController
{
    /**
     * Marks the user live on their stream for a few hours, as Go live does on the web, and answers as GET /api/v1/me
     * does.
     */
    public function update(UpdateApiUserLiveRequest $request, #[CurrentUser] User $user, UpdateUserLive $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->platform(), $request->url(), $request->integer('hours'));

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }

    /**
     * Ends the user's stream early, as End stream does on the web, and answers as GET /api/v1/me does.
     */
    public function destroy(#[CurrentUser] User $user, DeleteUserLive $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user);

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
