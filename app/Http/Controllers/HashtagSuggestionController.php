<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListHashtagSuggestionsRequest;
use App\Queries\HashtagSuggestionsQuery;
use Illuminate\Http\JsonResponse;

final readonly class HashtagSuggestionController
{
    public function index(ListHashtagSuggestionsRequest $request, HashtagSuggestionsQuery $suggestions): JsonResponse
    {
        return response()->json([
            'hashtags' => $suggestions->get($request->string('q')->value()),
        ]);
    }
}
