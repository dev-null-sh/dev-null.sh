<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListMentionSuggestionsRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\MentionSuggestionsQuery;
use App\Queries\OrganizationSuggestionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class MentionSuggestionController
{
    /**
     * Organizations are only suggested outside orgs, since mentions only link to them in commits.
     */
    public function index(#[CurrentUser] User $user, ListMentionSuggestionsRequest $request, MentionSuggestionsQuery $suggestions, OrganizationSuggestionsQuery $organizations): JsonResponse
    {
        return response()->json([
            'users' => $suggestions->get($user, $request->string('q')->value(), $request->org(), reply: $request->reply(), quote: $request->quote()),
            'organizations' => $request->org() instanceof Conversation ? [] : $organizations->get($request->string('q')->value()),
        ]);
    }
}
