<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchUsersRequest;
use App\Models\User;
use App\Queries\MentionSuggestionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class MentionSuggestionController
{
    public function index(#[CurrentUser] User $user, SearchUsersRequest $request, MentionSuggestionsQuery $suggestions): JsonResponse
    {
        return response()->json([
            'users' => $suggestions->get($user, $request->string('q')->value()),
        ]);
    }
}
