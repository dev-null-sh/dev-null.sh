<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserLane;
use App\Http\Requests\UpdateApiUserLaneRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserLaneController
{
    /**
     * Saves the user's color, as Save color does on the web, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserLaneRequest $request, #[CurrentUser] User $user, UpdateUserLane $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->lane(), $request->color());

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
