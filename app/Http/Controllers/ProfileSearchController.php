<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListProfileSearchRequest;
use App\Models\User;
use App\Queries\OrganizationSuggestionsQuery;
use App\Queries\UserSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class ProfileSearchController
{
    /**
     * The people matching a name or handle, and the organizations that already have a page, after them.
     */
    public function index(ListProfileSearchRequest $request, #[CurrentUser] ?User $user, UserSearchQuery $search, OrganizationSuggestionsQuery $organizations): JsonResponse
    {
        return response()->json([
            'users' => $search->get($request->string('q')->value(), $user),
            'organizations' => $organizations->get($request->string('q')->value()),
        ]);
    }
}
