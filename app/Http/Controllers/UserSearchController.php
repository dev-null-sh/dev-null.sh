<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchUsersRequest;
use App\Models\User;
use App\Queries\UserSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserSearchController
{
    public function index(SearchUsersRequest $request, #[CurrentUser] User $user, UserSearchQuery $search): JsonResponse
    {
        return response()->json([
            'users' => $search->get($request->string('q')->value(), $user),
        ]);
    }
}
