<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserRemotePreferences;
use App\Http\Requests\UpdateApiUserRemotePreferencesRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserRemotePreferencesController
{
    /**
     * Saves who can push to the user and whether they share pulls, as the settings menu on the web's Remotes page
     * does, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserRemotePreferencesRequest $request, #[CurrentUser] User $user, UpdateUserRemotePreferences $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->remotesFrom(), $request->boolean('pull_receipts'));

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
