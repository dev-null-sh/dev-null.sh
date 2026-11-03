<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Carbon\CarbonInterface;

final readonly class UpdateConversationPin
{
    public function handle(User $user, Conversation $conversation, bool $pinned): ?CarbonInterface
    {
        $participant = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->firstOrFail();

        $participant->update(['pinned_at' => $pinned ? ($participant->pinned_at ?? now()) : null]);

        event(new UsersUpdated([$user->id], UserUpdate::Inbox, ['conversation' => $conversation->id]));

        return $participant->pinned_at;
    }
}
