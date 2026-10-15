<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListUserSearchRequest;
use App\Queries\UserSearchQuery;
use Illuminate\Http\JsonResponse;

final readonly class UserSearchController
{
    public function index(ListUserSearchRequest $request, UserSearchQuery $search): JsonResponse
    {
        return response()->json([
            'users' => $search->get($request->string('q')->value()),
        ]);
    }
}
