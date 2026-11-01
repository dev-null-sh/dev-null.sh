<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowNotificationGroupRequest;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class NotificationGroupController
{
    public function show(ShowNotificationGroupRequest $request, #[CurrentUser] User $user, Notification $notification, NotificationGroupsQuery $query): JsonResponse
    {
        // Expanded replies, mentions and quotes can be starred from the inbox too, so they say how starred they are.
        return response()->json($query->withCommitStars($user, $query->members($user, $notification, $request->type())
            ->with('commit')->latest()->latest('id')->cursorPaginate(20)->withQueryString()));
    }
}
