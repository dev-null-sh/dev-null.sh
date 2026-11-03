<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class UpdateConversationReadThrough
{
    /**
     * Only ever moves forward, and never past the conversation's last message.
     */
    public function handle(User $user, Conversation $conversation, int $through): void
    {
        $through = min($through, $conversation->last_message_id ?? 0);

        $read = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('read_through_id', '<', $through)
            ->update(['read_through_id' => $through, 'read_at' => now()]);

        if ($read === 0) {
            return;
        }

        // The reader's unread counts change on their other devices, and whoever shares reads sees how far they got.
        UsersUpdated::dispatch([$user->id], UserUpdate::Inbox, ['conversation' => $conversation->id]);
        UsersUpdated::dispatch(
            ConversationParticipant::query()->where('conversation_id', $conversation->id)->whereNot('user_id', $user->id)->pluck('user_id'),
            UserUpdate::Conversation,
            ['conversation' => $conversation->id],
        );
    }
}
