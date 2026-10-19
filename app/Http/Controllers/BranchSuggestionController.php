<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListBranchSuggestionsRequest;
use App\Queries\BranchSuggestionsQuery;
use Illuminate\Http\JsonResponse;

final readonly class BranchSuggestionController
{
    public function index(ListBranchSuggestionsRequest $request, BranchSuggestionsQuery $suggestions): JsonResponse
    {
        return response()->json([
            'branches' => $suggestions->get($request->string('q')->value()),
        ]);
    }
}
