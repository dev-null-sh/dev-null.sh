<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiMentionSuggestionsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\MentionSuggestionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class MentionSuggestionController
{
    /**
     * The people the composer suggests for an @mention, as the web's composer suggests them.
     */
    public function index(ListApiMentionSuggestionsRequest $request, #[CurrentUser] User $user, MentionSuggestionsQuery $suggestions): AnonymousResourceCollection
    {
        return UserResource::collection($suggestions->get($user, $request->string('q')->value(), $request->org(), reply: $request->reply(), quote: $request->quote()));
    }
}
