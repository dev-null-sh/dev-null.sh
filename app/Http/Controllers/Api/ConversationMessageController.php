<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiConversationMessagesRequest;
use App\Http\Resources\ConversationThreadResource;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class ConversationMessageController
{
    /**
     * The latest page of the conversation with the partner, the page before a message, or everything after one with
     * the reactions changed since a version, as the web's thread polls it. Someone the user hasn't talked to yet has
     * an empty thread, which says whether a first message can go to them.
     */
    public function index(ListApiConversationMessagesRequest $request, #[CurrentUser] User $user, User $partner, ConversationThreadQuery $threads): ConversationThreadResource
    {
        return ConversationThreadResource::make([
            'partner' => $partner,
            ...$threads->get($user, $partner, $request->afterId(), $request->beforeId(), $request->reactionsAfter()),
        ]);
    }
}
