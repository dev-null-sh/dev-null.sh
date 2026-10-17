<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateConversation
{
    /**
     * The one conversation two people share. It opens straight away for the recipient, or arrives fetched as a
     * request when they don't take remotes from the sender.
     */
    public function handle(User $sender, User $recipient): Conversation
    {
        return DB::transaction(function () use ($sender, $recipient): Conversation {
            $conversation = Conversation::query()->createOrFirst(['pair' => Conversation::pairOf($sender, $recipient)]);

            ConversationParticipant::query()->createOrFirst(
                ['conversation_id' => $conversation->id, 'user_id' => $sender->id],
            );

            ConversationParticipant::query()->createOrFirst(
                ['conversation_id' => $conversation->id, 'user_id' => $recipient->id],
                ['status' => $recipient->acceptsRemotesFrom($sender) ? RemoteStatus::Open : RemoteStatus::Fetched],
            );

            return $conversation;
        });
    }
}
