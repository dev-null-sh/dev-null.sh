<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserFollowingRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserFollowingController
{
    /**
     * The people a user follows, 30 a page as on the web, with whether the signed-in user follows each one, and whether
     * each one follows them.
     */
    public function index(ListApiUserFollowingRequest $request, #[CurrentUser] User $currentUser, User $user, UserConnectionsQuery $connections): AnonymousResourceCollection
    {
        return UserResource::collection($connections->builder($user->following(), $currentUser)->cursorPaginate(30));
    }
}
