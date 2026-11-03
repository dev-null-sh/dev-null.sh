<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemoteStatus;
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

        // The reader's unread counts change on their other devices. Only whoever would see how far they read hears that
        // they read at all, by the same rules the conversation shows reads with: both share pulls, and in a direct
        // conversation the reader merged it.
        event(new UsersUpdated([$user->id], UserUpdate::Inbox, ['conversation' => $conversation->id]));

        $participants = ConversationParticipant::query()->where('conversation_id', $conversation->id)->with('user')->get();
        $reader = $participants->firstWhere('user_id', $user->id);
        $sharesReads = $user->pull_receipts && ($conversation->isOrg() || $reader?->status === RemoteStatus::Open);

        event(new UsersUpdated(
            $sharesReads ? $participants->filter(fn (ConversationParticipant $participant): bool => $participant->user_id !== $user->id && $participant->user->pull_receipts)->pluck('user_id') : [],
            UserUpdate::Conversation,
            ['conversation' => $conversation->id, 'read' => 1],
        ));
    }
}
