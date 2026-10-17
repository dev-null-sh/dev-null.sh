<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class CreateMessage
{
    /**
     * Makes the message the conversation's latest, for every member's inbox.
     */
    public static function advance(Conversation $conversation, Message $message, bool $unstash): void
    {
        Conversation::query()
            ->whereKey($conversation->id)
            ->where(fn (Builder $query): Builder => $query->whereNull('last_message_id')->orWhere('last_message_id', '<', $message->id))
            ->update(['last_message_id' => $message->id]);

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->update(['last_message_at' => $message->created_at, ...$unstash ? ['archived_at' => null] : []]);
    }

    /**
     * Sending the same client id again, as a retry does, returns the message it already created. A new message
     * brings the conversation back out of the stash for everyone in it.
     */
    public function handle(User $sender, Conversation $conversation, string $body, string $clientId): Message
    {
        $message = DB::transaction(function () use ($sender, $conversation, $body, $clientId): Message {
            $message = Message::query()->createOrFirst(
                ['user_id' => $sender->id, 'client_id' => $clientId],
                ['conversation_id' => $conversation->id, 'body' => $body],
            );

            if (! $message->wasRecentlyCreated) {
                return $message;
            }

            self::advance($conversation, $message, unstash: true);

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['status' => RemoteStatus::Open]);

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->where('read_through_id', '<', $message->id)
                ->update(['read_through_id' => $message->id, 'read_at' => now()]);

            return $message;
        });

        Cache::forget(UpdateConversationTyping::key($sender, $conversation));

        return $message;
    }
}
