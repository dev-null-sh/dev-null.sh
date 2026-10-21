<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class UpdateOrgMemberVisibility
{
    /**
     * Hiding again takes the member out of the list and the receipts, but what they already pushed still shows who
     * pushed it.
     */
    public function handle(Conversation $org, User $member, bool $isHidden): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $org->id)
            ->where('user_id', $member->id)
            ->update(['is_hidden' => $isHidden]);
    }
}
