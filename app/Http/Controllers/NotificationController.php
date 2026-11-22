<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListNotificationsRequest;
use App\Http\Resources\ShownStreams;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    public function index(ListNotificationsRequest $request, #[CurrentUser] User $user, NotificationGroupsQuery $query): Response
    {
        $type = $request->type($user);

        return Inertia::render('notification/index', [
            'notifications' => Inertia::scroll(function () use ($request, $user, $type, $query): CursorPaginator {
                $groups = $query->withStarredCommitImages($query->withQuotedCommits($user, $query->paginate($user, $type, $request->through())));

                return ShownStreams::notifications($groups, $query->commitStars($user, $groups->items()));
            })->matchOn('data.group_id'),
            'filters' => ['type' => $type],
            'readAt' => CarbonImmutable::now()->startOfSecond()->toJSON(),
        ]);
    }
}
