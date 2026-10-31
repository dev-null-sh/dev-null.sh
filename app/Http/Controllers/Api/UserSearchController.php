<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserSearchRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\UserSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserSearchController
{
    /**
     * The people matching a name or handle, as the web's search finds them, with whether the user follows each one and
     * whether each one follows them. People the user muted or blocked, or who blocked them, are left out.
     */
    public function index(ListApiUserSearchRequest $request, #[CurrentUser] User $user, UserSearchQuery $search): AnonymousResourceCollection
    {
        $users = new Collection($search->get($request->string('q')->value(), $user)->all());

        return UserResource::collection($users->loadExists([
            'followers as is_followed' => function (Builder|Relation $query) use ($user): void {
                $query->whereKey($user->id);
            },
            'following as follows_you' => function (Builder|Relation $query) use ($user): void {
                $query->whereKey($user->id);
            },
        ]));
    }
}
