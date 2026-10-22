<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationTyping;
use App\Http\Requests\UpdateBroadcastTypingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class BroadcastTypingController
{
    public function update(UpdateBroadcastTypingRequest $request, #[CurrentUser] User $user, Conversation $broadcast, UpdateConversationTyping $action): Response
    {
        $action->handle($user, $broadcast);

        return response()->noContent();
    }
}
