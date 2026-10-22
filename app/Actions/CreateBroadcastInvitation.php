<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\User;

final readonly class CreateBroadcastInvitation
{
    public function handle(Conversation $broadcast, User $invitee): void
    {
        BroadcastInvitation::query()->firstOrCreate([
            'conversation_id' => $broadcast->id,
            'user_id' => $invitee->id,
        ]);
    }
}
