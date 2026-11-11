<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserMutualsRequest;
use App\Http\Resources\UserConnectionCollection;
use App\Models\User;
use App\Queries\BioProfilesQuery;
use App\Queries\UserConnectionsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserMutualsController
{
    /**
     * The people a user follows who follow them back, 30 a page as on the web, with the same flags and bio profiles as
     * the followers list.
     */
    public function index(ListApiUserMutualsRequest $request, #[CurrentUser] User $currentUser, User $user, UserConnectionsQuery $connections, BioProfilesQuery $bioProfiles): UserConnectionCollection
    {
        $page = $connections->builder($connections->mutuals($user), $currentUser)->cursorPaginate(30);

        return new UserConnectionCollection($page, $bioProfiles->getMany($page->items()));
    }
}
