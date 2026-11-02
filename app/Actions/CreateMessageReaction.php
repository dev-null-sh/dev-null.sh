<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateMessageReaction
{
    public function __construct(private UpdateMessageReactionsVersion $updateMessageReactionsVersion)
    {
        //
    }

    /**
     * Inserted unless already there, so reacting twice with the same emoji leaves one reaction.
     *
     * The request checks the cap before this runs, but reactions sent at the same moment each see the others missing.
     * So the cap is counted again here, under the conversation's lock: a reaction past it changes nothing. The
     * conversation is the row locked first, as pins and removed reactions lock it before the message, so none of them
     * can deadlock with this.
     */
    public function handle(User $user, Message $message, string $emoji): void
    {
        DB::transaction(function () use ($user, $message, $emoji): void {
            Conversation::query()->whereKey($message->conversation_id)->lockForUpdate()->first();

            $reacted = MessageReaction::query()
                ->where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->pluck('emoji');

            if ($reacted->count() >= MessageReaction::MAX_PER_MESSAGE && $reacted->doesntContain($emoji)) {
                return;
            }

            $inserted = MessageReaction::query()->insertOrIgnore([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted > 0) {
                $this->updateMessageReactionsVersion->handle($message);
            }
        });
    }
}
