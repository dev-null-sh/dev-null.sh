<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Conversation;
use App\Models\User;

final readonly class DeleteOrgMentions
{
    /**
     * Clears the one mention the user opened, or every mention they have in the org.
     */
    public function handle(User $user, Conversation $org, ?int $messageId = null): void
    {
        $mentions = $user->mentions()->where('conversation_id', $org->id);

        if ($messageId !== null) {
            $mentions->where('messages.id', $messageId);
        }

        if ($user->mentions()->detach($mentions->pluck('messages.id')) === 0) {
            return;
        }

        event(new UsersUpdated([$user->id], UserUpdate::Conversation, ['conversation' => $org->id])->dontBroadcastToCurrentUser());
    }
}
