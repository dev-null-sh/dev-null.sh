<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Http\Requests\ListUserMutualsRequest;
use App\Models\User;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
            'users' => Inertia::scroll(fn () => $connections->builder($user->following()
                ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query->from('follows', 'follows_back')
                    ->whereColumn('follows_back.follower_id', 'users.id')
                    ->where('follows_back.followed_id', $user->id)), $currentUser)
                ->cursorPaginate(30)),
        ]);
    }
}
