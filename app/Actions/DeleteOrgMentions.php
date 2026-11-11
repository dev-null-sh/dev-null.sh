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
     * Clears the mentions the user opened or saw, or every mention they have in the org.
     *
     * @param  list<int>|null  $messageIds
     */
    public function handle(User $user, Conversation $org, ?array $messageIds = null): void
    {
        $mentions = $user->mentions()->where('conversation_id', $org->id);

        if ($messageIds !== null) {
            $mentions->whereIn('messages.id', $messageIds);
        }

        if ($user->mentions()->detach($mentions->pluck('messages.id')) === 0) {
            return;
        }

        event(new UsersUpdated([$user->id], UserUpdate::Conversation, ['conversation' => $org->id])->dontBroadcastToCurrentUser());
    }
}
