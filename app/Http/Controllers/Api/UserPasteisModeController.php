<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserPasteisMode;
use App\Http\Requests\UpdateApiUserPasteisModeRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserPasteisModeController
{
    /**
     * Turns pastéis mode on or off, as the switch in the web's settings does, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserPasteisModeRequest $request, #[CurrentUser] User $user, UpdateUserPasteisMode $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->boolean('enabled'));

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
