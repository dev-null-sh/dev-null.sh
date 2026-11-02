<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListIssueSuggestionsRequest;
use App\Queries\IssueSuggestionsQuery;
use Illuminate\Http\JsonResponse;

final readonly class IssueSuggestionController
{
    public function index(ListIssueSuggestionsRequest $request, IssueSuggestionsQuery $suggestions): JsonResponse
    {
        return response()->json(['issue' => $suggestions->get($request->integer('q'))]);
    }
}
