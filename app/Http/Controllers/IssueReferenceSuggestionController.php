<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListIssueReferenceSuggestionsRequest;
use App\Queries\IssueReferenceSuggestionsQuery;
use Illuminate\Http\JsonResponse;

final readonly class IssueReferenceSuggestionController
{
    public function index(
        ListIssueReferenceSuggestionsRequest $request,
        IssueReferenceSuggestionsQuery $suggestions,
    ): JsonResponse {
        return response()->json([
            'references' => $suggestions->get($request->digits()),
        ]);
    }
}
