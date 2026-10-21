<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class DeleteConversationMute
{
    public function handle(User $user, Conversation $conversation): void
    {
        ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->update(['muted_at' => null]);
    }
}
