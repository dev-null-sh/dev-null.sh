<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class DeleteBroadcastSubscription
{
    /**
     * A subscriber leaves. Their participant row stays, with the cutoff their first join set, so coming back picks up
     * where the history they have already seen ends; until then they see nothing of the broadcast.
     */
    public function handle(User $subscriber, Conversation $broadcast): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $broadcast->id)
            ->where('user_id', $subscriber->id)
            ->where('status', '!=', RemoteStatus::Banned)
            ->update(['status' => RemoteStatus::Dropped, 'archived_at' => null]);
    }
}
