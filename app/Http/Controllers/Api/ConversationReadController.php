<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationReadThrough;
use App\Http\Requests\UpdateApiConversationReadRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationReadController
{
    /**
     * Pulls the conversation through a message, as the web does once the thread is scrolled to it. It only ever moves
     * forward, and a conversation that hasn't started has nothing to pull.
     */
    public function update(UpdateApiConversationReadRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationReadThrough $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation, $request->integer('through'));
        }

        return response()->noContent();
    }
}
