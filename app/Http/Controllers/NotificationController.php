<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateNotificationsReadAt;
use App\Http\Requests\ListNotificationsRequest;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    public function index(ListNotificationsRequest $request, #[CurrentUser] User $user, NotificationGroupsQuery $query, UpdateNotificationsReadAt $action): Response
    {
        $readAt = $request->loadsNotifications() ? $action->handle($user) : null;
        $type = $request->type($user);

        return Inertia::render('notification/index', [
            'notifications' => Inertia::scroll(fn (): CursorPaginator => $query->withStarredCommitImages($query->withQuotedCommits($user, $query->paginate($user, $type, $request->through()))))->matchOn('data.group_id'),
            'filters' => ['type' => $type],
            'readAt' => $readAt?->toJSON(),
        ]);
    }
}
