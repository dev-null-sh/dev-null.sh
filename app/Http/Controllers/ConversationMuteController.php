<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationMute;
use App\Actions\DeleteConversationMute;
use App\Http\Requests\CreateConversationMuteRequest;
use App\Http\Requests\DeleteConversationMuteRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationMuteController
{
    public function store(CreateConversationMuteRequest $request, #[CurrentUser] User $user, User $partner, CreateConversationMute $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }

    public function destroy(DeleteConversationMuteRequest $request, #[CurrentUser] User $user, User $partner, DeleteConversationMute $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }
}
