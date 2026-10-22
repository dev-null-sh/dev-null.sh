<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationTyping;
use App\Http\Requests\UpdateApiConversationTypingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationTypingController
{
    /**
     * Says the user is typing to the partner for a few seconds, as the web's composer does every few seconds while it
     * has text. A conversation that hasn't started has nobody to tell.
     */
    public function update(UpdateApiConversationTypingRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationTyping $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }
}
