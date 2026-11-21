<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserTermsAcceptance;
use App\Http\Requests\UpdateApiUserTermsRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserTermsController
{
    /**
     * Records that the person ticked the box for the terms and privacy policy in the app, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserTermsRequest $request, #[CurrentUser] User $user, UpdateUserTermsAcceptance $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user);

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
