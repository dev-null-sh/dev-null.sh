<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserProfile;
use App\Exceptions\ApiException;
use App\Http\Requests\UpdateApiUserProfileRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserProfileController
{
    /**
     * Mirrors the user's GitHub profile again, as Sync from GitHub does on the web, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserProfileRequest $request, #[CurrentUser] User $user, UpdateUserProfile $action, NotificationGroupsQuery $notifications): CurrentUserResource|JsonResponse
    {
        if (! $action->handle($user)) {
            return ApiException::gitHubSyncFailed()->render();
        }

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
