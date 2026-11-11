<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserFollowingRequest;
use App\Http\Resources\UserConnectionCollection;
use App\Models\User;
use App\Queries\BioProfilesQuery;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserFollowingController
{
    /**
     * The people a user follows, 30 a page as on the web, with whether the signed-in user follows each one, whether each
     * one follows them, and the profiles each one's bio mentions.
     */
    public function index(ListApiUserFollowingRequest $request, #[CurrentUser] User $currentUser, User $user, UserConnectionsQuery $connections, BioProfilesQuery $bioProfiles): UserConnectionCollection
    {
        $page = $connections->builder($user->following(), $currentUser)->cursorPaginate(30);

        return new UserConnectionCollection($page, $bioProfiles->getMany($page->items()));
    }
}
