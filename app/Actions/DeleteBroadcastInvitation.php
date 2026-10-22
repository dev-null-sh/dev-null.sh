<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\User;

final readonly class DeleteBroadcastInvitation
{
    /**
     * The owner takes an invitation back, or the person it was for declines it.
     */
    public function handle(Conversation $broadcast, User $invitee): void
    {
        BroadcastInvitation::query()->where('conversation_id', $broadcast->id)->where('user_id', $invitee->id)->delete();
    }
}
