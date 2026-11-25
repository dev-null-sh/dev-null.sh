<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserEventTheme;
use App\Http\Requests\UpdateApiUserEventThemeRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserEventThemeController
{
    /**
     * Saves the event theme, as Settings → Appearance does on the web, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserEventThemeRequest $request, #[CurrentUser] User $user, UpdateUserEventTheme $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->choice());

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
