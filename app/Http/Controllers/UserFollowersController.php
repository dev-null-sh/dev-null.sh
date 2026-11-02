<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Http\Requests\ListUserFollowersRequest;
use App\Models\User;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowersController
{
    public function index(ListUserFollowersRequest $request, #[CurrentUser] User $currentUser, User $user, UserConnectionsQuery $connections): Response
    {
        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => ConnectionType::Followers,
            'users' => Inertia::scroll(fn () => $connections->builder($user->followers(), $currentUser)
                ->cursorPaginate(30)),
        ]);
    }
}
