<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListNotificationsRequest;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    public function index(ListNotificationsRequest $request, #[CurrentUser] User $user, NotificationGroupsQuery $query): Response
    {
        return Inertia::render('notification/index', [
            'notifications' => Inertia::scroll(fn (): CursorPaginator => $query->get($user, $request->type(), $request->unread()))->matchOn('data.group_id'),
            'filters' => ['type' => $request->type(), 'unread' => $request->unread()],
        ]);
    }
}
