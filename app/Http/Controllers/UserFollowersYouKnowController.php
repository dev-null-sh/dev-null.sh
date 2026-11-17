<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Http\Requests\ListUserFollowersYouKnowRequest;
use App\Models\User;
use App\Queries\FollowersYouKnowQuery;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowersYouKnowController
{
    public function index(ListUserFollowersYouKnowRequest $request, #[CurrentUser] User $currentUser, User $user, FollowersYouKnowQuery $followersYouKnow, UserConnectionsQuery $connections): Response
    {
        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => ConnectionType::FollowersYouKnow,
            'loadedDigest' => Inertia::optional(fn (): array => ['users' => $connections->loadedDigest($followersYouKnow->builder($user, $currentUser), $request->loaded('users'))]),
            'users' => Inertia::scroll(fn () => $followersYouKnow->builder($user, $currentUser)
                ->withExists([
                    'followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($currentUser->id),
                    'following as follows_you' => fn (Builder $query): Builder => $query->whereKey($currentUser->id),
                ])
                ->cursorPaginate(30)),
        ]);
    }
}
