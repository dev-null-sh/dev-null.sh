<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationTyping;
use App\Http\Requests\UpdateConversationTypingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationTypingController
{
    public function update(UpdateConversationTypingRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationTyping $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }
}
