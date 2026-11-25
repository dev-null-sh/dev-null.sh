<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Http\Requests\ListUserMutualsRequest;
use App\Models\User;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserMutualsController
{
    /**
     * The people a user follows who follow them back.
     */
    public function index(ListUserMutualsRequest $request, #[CurrentUser] User $currentUser, User $user, UserConnectionsQuery $connections): Response
    {
        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => ConnectionType::Mutuals,
            'loadedDigest' => Inertia::optional(fn (): array => ['users' => $connections->loadedDigest($connections->builder($connections->mutuals($user), $currentUser), $request->loaded('users'))]),
            'users' => Inertia::scroll(fn () => $connections->builder($connections->mutuals($user), $currentUser)->cursorPaginate(30)),
        ]);
    }
}
