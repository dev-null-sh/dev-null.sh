<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RemoteStatus;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Services\RemotePush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Date;

#[Tries(3)]
final class SendRemotePush implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $messageId)
    {
        //
    }

    public function handle(RemotePush $push): void
    {
        $message = Message::query()->find($this->messageId);

        if (! $message instanceof Message || $message->event !== null || $message->created_at->lt(Date::now()->subMinutes(5))
            || ! config('services.web_push.public_key') || ! config('services.web_push.private_key')) {
            return;
        }

        $participants = ConversationParticipant::query()->where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $message->user_id)
            ->where('visible_from_id', '<', $message->id)
            ->where('read_through_id', '<', $message->id)
            ->whereIn('status', config('remotes.requests') ? [RemoteStatus::Open, RemoteStatus::Fetched] : [RemoteStatus::Open])
            ->where(fn (Builder $query): Builder => $query->whereNull('muted_until_at')->orWhere('muted_until_at', '<=', now()))
            ->with('user')->get();

        $retry = false;

        foreach ($participants as $participant) {
            $user = $participant->user;

            if (! $user->remotes_enabled || $user->hasBlockWith($message->user) || $user->isMuting($message->user)) {
                continue;
            }

            foreach (PushSubscription::query()->where('user_id', $user->id)->lazyById() as $subscription) {
                if (! $push->send($subscription)) {
                    $retry = true;
                }
            }
        }

        if ($retry) {
            $this->release(30);
        }
    }
}
