<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\PushNotificationProvider;
use App\Enums\OrgEvent;
use App\Enums\RemoteStatus;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Date;

#[Timeout(210)]
final class SendRemotePush implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>|null  $subscriptionIds
     */
    public function __construct(
        public readonly int $messageId,
        public readonly string $userId,
        public readonly ?array $subscriptionIds = null,
        public readonly int $tries = 3,
    ) {
        //
    }

    public function handle(PushNotificationProvider $push): void
    {
        $message = Message::query()->find($this->messageId);

        if (! $message instanceof Message || ! in_array($message->event, [null, OrgEvent::Ping], true) || $message->created_at->lt(Date::now()->subMinutes(5))) {
            return;
        }

        $participant = ConversationParticipant::query()->where('conversation_id', $message->conversation_id)
            ->where('user_id', $this->userId)
            ->where('user_id', '!=', $message->user_id)
            ->where('visible_from_id', '<', $message->id)
            ->where('read_through_id', '<', $message->id)
            ->whereIn('status', config('remotes.requests') ? [RemoteStatus::Open, RemoteStatus::Fetched] : [RemoteStatus::Open])
            ->where(fn (Builder $query): Builder => $query->whereNull('muted_until_at')->orWhere('muted_until_at', '<=', now()))
            ->whereHas('user.pushSubscriptions')
            ->with('user')->first();

        if (! $participant instanceof ConversationParticipant) {
            return;
        }

        $user = $participant->user;

        if (! $user->remotes_enabled || $user->hasBlockWith($message->user) || $user->isMuting($message->user)) {
            return;
        }

        $failed = [];

        foreach (PushSubscription::query()->where('user_id', $user->id)
            ->when($this->subscriptionIds !== null, fn (Builder $query): Builder => $query->whereKey($this->subscriptionIds))
            ->orderBy('id')->limit(PushSubscription::MAX_PER_USER)->get() as $subscription) {
            if (! $push->send($subscription)) {
                $failed[] = $subscription->id;
            }
        }

        if ($failed !== [] && $this->attempts() < $this->tries) {
            // A follow-up starts with a fresh queue attempt count, so carry the remaining budget.
            dispatch(new self($this->messageId, $this->userId, $failed, $this->tries - $this->attempts())
                ->onConnection($this->connection)->onQueue($this->queue))->delay(now()->addSeconds(30));
        }
    }
}
