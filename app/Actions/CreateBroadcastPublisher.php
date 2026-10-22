<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class CreateBroadcastPublisher
{
    /**
     * The owner lets a subscriber post alongside them.
     */
    public function handle(Conversation $broadcast, User $subscriber): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $broadcast->id)
            ->where('user_id', $subscriber->id)
            ->update(['role' => BroadcastRole::Publisher]);
    }
}
