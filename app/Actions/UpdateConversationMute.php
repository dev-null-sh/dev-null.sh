<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConversationMuteDuration;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class UpdateConversationMute
{
    /**
     * Muting is the user's own: everyone else keeps hearing the conversation.
     */
    public function handle(User $user, Conversation $conversation, ConversationMuteDuration $duration): ?CarbonImmutable
    {
        $until = $duration->until($user->timezone);

        ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->update(['muted_until_at' => $until]);

        return $until;
    }
}
