<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class CreateConversationMute
{
    /**
     * Unlike stashing, muting outlasts new messages: the conversation stays in the inbox but leaves the nav badge.
     */
    public function handle(User $user, Conversation $conversation): void
    {
        ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->whereNull('muted_at')
            ->update(['muted_at' => now()]);
    }
}
