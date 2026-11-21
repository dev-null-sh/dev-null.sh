<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiProfileSearchRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\OrganizationSuggestionsQuery;
use App\Queries\UserSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

final readonly class ProfileSearchController
{
    /**
     * The people and the organizations matching a name or handle, as the web's search finds them. People come with
     * whether the user follows each one and whether each one follows them, and people the user muted or blocked, or
     * who blocked them, are left out. Organizations are only those that already have a page, with whether the user
     * follows each one.
     */
    public function index(ListApiProfileSearchRequest $request, #[CurrentUser] User $user, UserSearchQuery $users, OrganizationSuggestionsQuery $organizations): JsonResponse
    {
        $term = $request->string('q')->value();

        return response()->json([
            'users' => UserResource::collection(new Collection($users->get($term, $user)->all())->loadExists([
                'followers as is_followed' => function (Builder|Relation $query) use ($user): void {
                    $query->whereKey($user->id);
                },
                'following as follows_you' => function (Builder|Relation $query) use ($user): void {
                    $query->whereKey($user->id);
                },
            ])),
            'organizations' => OrganizationResource::collection(new Collection($organizations->get($term)->all())->loadExists([
                'followers as is_followed' => function (Builder|Relation $query) use ($user): void {
                    $query->whereKey($user->id);
                },
            ])),
        ]);
    }
}
