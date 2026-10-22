<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class DeleteBroadcastPublisher
{
    /**
     * The owner takes posting back from a publisher, who keeps reading as a subscriber.
     */
    public function handle(Conversation $broadcast, User $publisher): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $broadcast->id)
            ->where('user_id', $publisher->id)
            ->update(['role' => BroadcastRole::Subscriber]);
    }
}
