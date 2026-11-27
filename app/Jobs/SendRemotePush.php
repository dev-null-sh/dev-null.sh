<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\PushNotificationProvider;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use App\Queries\RemotesPushRecipientQuery;
use App\Support\PushRetryDelay;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;

/**
 * Pushes a Remotes message or ping to the browsers of one recipient. Phones get their own job, so each has a full
 * execution budget and a retry schedule of its own.
 */
#[Timeout(210)]
final class SendRemotePush implements ShouldQueue
{
    use Queueable;

    private const int DEFAULT_TRIES = 3;

    /**
     * @param  list<string>|null  $subscriptionIds
     */
    public function __construct(
        public readonly int $messageId,
        public readonly string $userId,
        public readonly ?array $subscriptionIds = null,
        public readonly int $tries = self::DEFAULT_TRIES,
    ) {
        //
    }

    /**
     * Queues a push of the message to everyone else in its conversation, browsers and phones in jobs of their own.
     */
    public static function dispatchFor(Message $message): void
    {
        foreach (ConversationParticipant::query()->where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $message->user_id)->whereHas('user', self::reachable(...))
            ->with(['user' => fn (BelongsTo $query): BelongsTo => $query->withExists([
                'pushSubscriptions',
                'devices' => fn (Builder $devices): Builder => $devices->whereNotNull('push_token_hash'),
            ])])->get() as $participant) {
            $user = $participant->user;

            if ($user->getAttribute('push_subscriptions_exists') === true) {
                dispatch(new self($message->id, $user->id))->delay(now()->addSeconds(10))->afterCommit();
            }

            if ($user->getAttribute('devices_exists') === true) {
                dispatch(new SendRemotePhonePush($message->id, $user->id))->delay(now()->addSeconds(10))->afterCommit();
            }
        }
    }

    public function handle(PushNotificationProvider $push): void
    {
        $message = Message::query()->find($this->messageId);

        if (! $message instanceof Message) {
            return;
        }

        if (! RemotesPushRecipientQuery::forBrowsers()->get($message, $this->userId) instanceof ConversationParticipant) {
            return;
        }

        $failed = [];

        foreach (PushSubscription::query()->where('user_id', $this->userId)
            ->when($this->subscriptionIds !== null, fn (Builder $query): Builder => $query->whereKey($this->subscriptionIds))
            ->orderBy('id')->limit(PushSubscription::MAX_PER_USER)->get() as $subscription) {
            if (! $push->send($subscription)) {
                $failed[] = $subscription->id;
            }
        }

        if ($failed === [] || $this->attempts() >= $this->tries) {
            return;
        }

        // A follow-up starts with a fresh queue attempt count, so carry the remaining budget.
        dispatch(new self($this->messageId, $this->userId, $failed, $this->tries - $this->attempts()))
            ->onConnection($this->connection)->onQueue($this->queue)->delay(now()->addSeconds($this->retryDelay()));
    }

    /**
     * @param  Builder<User>  $users
     * @return Builder<User>
     */
    private static function reachable(Builder $users): Builder
    {
        return $users->where(fn (Builder $query): Builder => $query->has('pushSubscriptions')
            ->orWhereHas('devices', fn (Builder $devices): Builder => $devices->whereNotNull('push_token_hash')));
    }

    /**
     * Twice as long each attempt: 30 seconds, then 60.
     */
    private function retryDelay(): int
    {
        // A follow-up carries what is left of the budget, so the remainder says which attempt this is.
        return PushRetryDelay::secondsFor(self::DEFAULT_TRIES - $this->tries + $this->attempts(), null);
    }
}
