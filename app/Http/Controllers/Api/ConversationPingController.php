<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateConversationPing;
use App\Http\Requests\CreateApiConversationPingRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class ConversationPingController
{
    /**
     * Pings the partner, as the web's composer does, in a conversation that has already started.
     */
    public function store(CreateApiConversationPingRequest $request, #[CurrentUser] User $user, User $partner, CreateConversationPing $action): MessageResource
    {
        return MessageResource::make($action->handle($user, Conversation::betweenOrFail($user, $partner)));
    }
}
