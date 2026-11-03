<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemotesFrom;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\ConversationParticipant;
use App\Models\User;

final readonly class UpdateUserRemotePreferences
{
    /**
     * Applies to conversations started from now on: requests already fetched stay fetched.
     */
    public function handle(User $user, RemotesFrom $from, bool $pullReceipts): void
    {
        $user->update(['remotes_from' => $from, 'pull_receipts' => $pullReceipts]);

        // Sharing reads or not shows in every conversation they have.
        UsersUpdated::dispatch([$user->id], UserUpdate::Account);
        UsersUpdated::dispatch(
            ConversationParticipant::query()
                ->whereIn('conversation_id', ConversationParticipant::query()->where('user_id', $user->id)->select('conversation_id'))
                ->whereNot('user_id', $user->id)
                ->distinct()
                ->pluck('user_id'),
            UserUpdate::Conversation,
        );
    }
}
