<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemoteStatus;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class UpdateConversationStatus
{
    /**
     * Merges a fetched request into the user's open conversations, or drops it.
     */
    public function handle(User $user, Conversation $conversation, RemoteStatus $status): void
    {
        ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->where('status', RemoteStatus::Fetched)
            ->update(['status' => $status]);

        // Settling a request changes the requests both of them see, and whether they share reads.
        UsersUpdated::dispatch(
            ConversationParticipant::query()->where('conversation_id', $conversation->id)->pluck('user_id'),
            UserUpdate::Inbox,
            ['conversation' => $conversation->id],
        );
    }
}
