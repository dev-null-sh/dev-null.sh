<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiGuildSuggestionsRequest;
use App\Http\Resources\GuildBadgeResource;
use App\Queries\GuildsQuery;
use Illuminate\Http\JsonResponse;

final readonly class GuildSuggestionController
{
    /**
     * The guilds the composer suggests for a #hashtag, as the web's composer suggests them: six at most whose handle
     * or name contains the term, those whose handle starts with it first, each group by the most members, then handle.
     */
    public function index(ListApiGuildSuggestionsRequest $request, GuildsQuery $query): JsonResponse
    {
        return GuildBadgeResource::collection($query->suggestions($request->term()))
            ->response()
            ->header('Cache-Control', 'private, no-store');
    }
}
