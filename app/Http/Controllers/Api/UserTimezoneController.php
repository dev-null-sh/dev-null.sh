<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserTimezone;
use App\Http\Requests\UpdateApiUserTimezoneRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserTimezoneController
{
    /**
     * Saves the timezone the web shows times in, as Settings does on the web, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserTimezoneRequest $request, #[CurrentUser] User $user, UpdateUserTimezone $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->timezone(), $request->showLocalTime());

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
