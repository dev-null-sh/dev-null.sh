<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListSuggestedUserStatesRequest;
use App\Models\User;
use App\Queries\SuggestedUserStatesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class SuggestedUserStateController
{
    public function index(ListSuggestedUserStatesRequest $request, #[CurrentUser] User $user, SuggestedUserStatesQuery $states): JsonResponse
    {
        return response()->json(['users' => $states->get($user, $request->ids())])
            ->header('Cache-Control', 'private, no-store');
    }
}
