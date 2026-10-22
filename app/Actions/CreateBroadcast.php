<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateBroadcast
{
    /**
     * The creator owns the broadcast, alone, and sees it from the very first message.
     */
    public function handle(User $owner, string $name, BroadcastVisibility $visibility): Conversation
    {
        return DB::transaction(function () use ($owner, $name, $visibility): Conversation {
            $broadcast = Conversation::query()->create([
                'kind' => ConversationKind::Broadcast,
                'name' => $name,
                'visibility' => $visibility,
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $broadcast->id,
                'user_id' => $owner->id,
                'role' => BroadcastRole::Owner,
                'last_message_at' => now(),
            ]);

            return $broadcast;
        });
    }
}
