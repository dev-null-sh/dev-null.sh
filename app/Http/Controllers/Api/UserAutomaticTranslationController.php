<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserAutomaticTranslation;
use App\Http\Requests\UpdateApiUserAutomaticTranslationRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserAutomaticTranslationController
{
    /**
     * Turns automatic translation on or off, as Settings does on the web, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserAutomaticTranslationRequest $request, #[CurrentUser] User $user, UpdateUserAutomaticTranslation $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->boolean('enabled'));

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
