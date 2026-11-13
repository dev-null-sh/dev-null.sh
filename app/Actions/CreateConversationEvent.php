<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateConversationEvent
{
    /**
     * Records a system event in a remote's history. It is read straight away by whoever made it and doesn't
     * bring the remote out of anyone's stash.
     */
    public function handle(User $actor, Conversation $org, OrgEvent $event, ?User $subject = null, string $body = ''): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $org->id,
            'user_id' => $actor->id,
            'client_id' => Str::uuid()->toString(),
            'body' => $body,
            'event' => $event,
            'subject_id' => $subject?->id,
        ]);

        CreateMessage::advance($org, $message, unstash: false);

        ConversationParticipant::query()
            ->where('conversation_id', $org->id)
            ->where('user_id', $actor->id)
            ->update(['read_through_id' => $message->id]);

        return $message;
    }
}
