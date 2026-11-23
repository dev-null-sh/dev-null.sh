<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListGuildSuggestionsRequest;
use App\Http\Resources\GuildBadgeResource;
use App\Queries\GuildsQuery;
use Illuminate\Http\JsonResponse;

final readonly class GuildSuggestionController
{
    public function index(ListGuildSuggestionsRequest $request, GuildsQuery $query): JsonResponse
    {
        return response()->json([
            'guilds' => GuildBadgeResource::collection($request->has('handles')
                ? $query->references($request->handles())
                : $query->suggestions($request->string('q')->trim()->lower()->value())),
        ])->header('Cache-Control', 'private, no-store');
    }
}
