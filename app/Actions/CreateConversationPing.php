<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Jobs\SendRemotePush;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateConversationPing
{
    /**
     * Records a ping, as MSN Messenger had, in a conversation's or an org's history. Like a message, it brings the remote
     * back out of the stash for everyone in it, is read straight away by whoever sent it, and pushes to the others.
     */
    public function handle(User $sender, Conversation $conversation): Message
    {
        return DB::transaction(function () use ($sender, $conversation): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $sender->id,
                'client_id' => Str::uuid()->toString(),
                'body' => '',
                'event' => OrgEvent::Ping,
            ]);

            CreateMessage::advance($conversation, $message, unstash: true);

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['read_through_id' => $message->id, 'read_at' => now()]);

            foreach (ConversationParticipant::query()->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $sender->id)->whereHas('user.pushSubscriptions')->get(['user_id']) as $participant) {
                dispatch(new SendRemotePush($message->id, $participant->user_id))->delay(now()->addSeconds(10))->afterCommit();
            }

            return $message;
        });
    }
}
