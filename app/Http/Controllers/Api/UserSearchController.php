<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListUserSearchRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\UserSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserSearchController
{
    /**
     * Up to 8 people whose username or name contains the term, best matches first, as the web's search box finds them.
     * A signed request leaves out the people its user muted, and those on either side of a block.
     */
    public function index(ListUserSearchRequest $request, #[CurrentUser] ?User $currentUser, UserSearchQuery $search): AnonymousResourceCollection
    {
        return UserResource::collection($search->get($request->string('q')->value(), $currentUser));
    }
}
