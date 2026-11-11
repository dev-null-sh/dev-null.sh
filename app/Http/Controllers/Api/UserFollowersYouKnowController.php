<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserFollowersYouKnowRequest;
use App\Http\Resources\UserConnectionCollection;
use App\Models\User;
use App\Queries\BioProfilesQuery;
use App\Queries\FollowersYouKnowQuery;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserFollowersYouKnowController
{
    /**
     * A user's followers whom the signed-in user follows too, 30 a page as on the web, with the same flags and bio
     * profiles as the followers list.
     */
    public function index(ListApiUserFollowersYouKnowRequest $request, #[CurrentUser] User $currentUser, User $user, FollowersYouKnowQuery $followersYouKnow, UserConnectionsQuery $connections, BioProfilesQuery $bioProfiles): UserConnectionCollection
    {
        $page = $connections->builder($followersYouKnow->builder($user, $currentUser), $currentUser)->cursorPaginate(30);

        return new UserConnectionCollection($page, $bioProfiles->getMany($page->items()));
    }
}
