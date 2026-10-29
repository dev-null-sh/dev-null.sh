<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserFollowedOrganizationsRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\User;
use App\Queries\FollowedOrganizationsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserFollowedOrganizationController
{
    /**
     * The organizations a user follows, 30 a page, newest follow first, with whether the signed-in user follows each one.
     */
    public function index(ListApiUserFollowedOrganizationsRequest $request, #[CurrentUser] User $currentUser, User $user, FollowedOrganizationsQuery $organizations): AnonymousResourceCollection
    {
        return OrganizationResource::collection($organizations->builder($user, $currentUser)->cursorPaginate(30));
    }
}
