<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\LiveFollowingQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class LiveFollowingController
{
    /**
     * A few of the people the user follows who are live right now, as the web's Live now aside shows them.
     */
    public function index(#[CurrentUser] User $user, LiveFollowingQuery $live): AnonymousResourceCollection
    {
        return UserResource::collection($live->get($user));
    }
}
