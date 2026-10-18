<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteMessageReaction
{
    public function __construct(private UpdateMessageReactionsVersion $updateMessageReactionsVersion)
    {
        //
    }

    public function handle(User $user, Message $message, string $emoji): void
    {
        DB::transaction(function () use ($user, $message, $emoji): void {
            $deleted = MessageReaction::query()
                ->where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->where('emoji', $emoji)
                ->delete();

            if ($deleted > 0) {
                $this->updateMessageReactionsVersion->handle($message);
            }
        });
    }
}
