<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListGuildMemberSuggestionsRequest;
use App\Models\Guild;
use App\Queries\GuildMemberSuggestionsQuery;
use Illuminate\Http\JsonResponse;

final readonly class GuildMemberSuggestionController
{
    public function index(ListGuildMemberSuggestionsRequest $request, Guild $guild, GuildMemberSuggestionsQuery $suggestions): JsonResponse
    {
        return response()->json(['members' => $suggestions->get($guild, $request->string('q')->value())]);
    }
}
