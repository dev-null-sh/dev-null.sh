<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiIssueSuggestionsRequest;
use App\Queries\IssueSuggestionsQuery;
use Illuminate\Http\JsonResponse;

final readonly class IssueSuggestionController
{
    /**
     * The /dev/null issue with that number, as the web's composer suggests it, or null.
     */
    public function index(ListApiIssueSuggestionsRequest $request, IssueSuggestionsQuery $suggestions): JsonResponse
    {
        return response()->json(['issue' => $suggestions->get($request->integer('q'))]);
    }
}
