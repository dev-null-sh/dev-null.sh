<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;

final readonly class UpdateMessageReactionsVersion
{
    /**
     * Polls ask for the reactions changed since a version, so each change takes the conversation's next version and
     * marks its message with it. Pinning and unpinning move the version too, so polls hand an org's pinned messages
     * over again. Bumping the conversation locks its row until the change commits, keeping versions in the order they
     * become visible.
     */
    public function handle(Message $message): void
    {
        $conversation = Conversation::query()->whereKey($message->conversation_id)->toBase();

        $conversation->increment('reactions_version');

        Message::query()->whereKey($message->id)->toBase()->update(['reactions_version' => $conversation->value('reactions_version')]);

        event(new UsersUpdated(ConversationParticipant::query()->where('conversation_id', $message->conversation_id)->pluck('user_id'), UserUpdate::Conversation, ['conversation' => $message->conversation_id]));
    }
}
