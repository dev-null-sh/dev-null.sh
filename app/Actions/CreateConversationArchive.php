<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class CreateConversationArchive
{
    /**
     * Stashing is the user's own: everyone else keeps the conversation in their inbox.
     */
    public function handle(User $user, Conversation $conversation): void
    {
        ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        event(new UsersUpdated([$user->id], UserUpdate::Inbox, ['conversation' => $conversation->id]));
    }
}
