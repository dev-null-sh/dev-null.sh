<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserRemotes;
use App\Http\Requests\UpdateApiUserRemotesRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserRemotesController
{
    /**
     * Turns Remotes on or off, as the switch in the web's settings does, and answers as GET /api/v1/me does. While
     * they are off, nobody can message the user and the conversations wait for them to come back.
     */
    public function update(UpdateApiUserRemotesRequest $request, #[CurrentUser] User $user, UpdateUserRemotes $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->boolean('enabled'));

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
