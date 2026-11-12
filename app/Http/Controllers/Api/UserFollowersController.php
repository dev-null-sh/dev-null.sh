<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserFollowersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserFollowersController
{
    /**
     * The people following a user, 30 a page as on the web, with whether the signed-in user follows each one, and whether
     * each one follows them.
     */
    public function index(ListApiUserFollowersRequest $request, #[CurrentUser] User $currentUser, User $user, UserConnectionsQuery $connections): AnonymousResourceCollection
    {
        return UserResource::collection($connections->builder($user->followers(), $currentUser)->cursorPaginate(30));
    }
}
