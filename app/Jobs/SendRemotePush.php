<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\DevicePushProvider;
use App\Contracts\Services\PushNotificationProvider;
use App\Dto\DevicePush;
use App\Enums\ConversationKind;
use App\Enums\OrgEvent;
use App\Enums\PushPreference;
use App\Enums\RemoteStatus;
use App\Models\ConversationParticipant;
use App\Models\Device;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Date;
use Throwable;

#[Timeout(210)]
final class SendRemotePush implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>|null  $subscriptionIds
     * @param  list<string>|null  $deviceIds
     */
    public function __construct(
        public readonly int $messageId,
        public readonly string $userId,
        public readonly ?array $subscriptionIds = null,
        public readonly int $tries = 3,
        public readonly ?array $deviceIds = null,
    ) {
        //
    }

    /**
     * Queues a push of the message to everyone else in its conversation with a browser or a phone to push to.
     */
    public static function dispatchFor(Message $message): void
    {
        foreach (ConversationParticipant::query()->where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $message->user_id)->whereHas('user', self::reachable(...))->get(['user_id']) as $participant) {
            dispatch(new self($message->id, $participant->user_id))->delay(now()->addSeconds(10))->afterCommit();
        }
    }

    /**
     * Browsers get an empty push whenever the recipient subscribed one. Phones show who wrote, and only while the
     * recipient keeps the switch for the conversation's kind on.
     */
    public function handle(PushNotificationProvider $push, DevicePushProvider $devicePush): void
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
            ->whereHas('user', self::reachable(...))
            ->with('user')->first();

        if (! $participant instanceof ConversationParticipant) {
            return;
        }

        $user = $participant->user;

        if (! $user->remotes_enabled || $user->hasBlockWith($message->user) || $user->isMuting($message->user)) {
            return;
        }

        $failedDevices = [];
        $conversation = $message->conversation;

        if (PushPreference::forConversation($conversation->kind)->isOnFor($user)) {
            $sender = $message->user->profile;
            $name = $sender->name !== '' ? $sender->name : '@'.$sender->handle;
            $pinged = $message->event === OrgEvent::Ping;
            $devicePushMessage = new DevicePush(
                $conversation->kind === ConversationKind::Org ? (string) $conversation->name : $name,
                match (true) {
                    $conversation->kind === ConversationKind::Org => $name.($pinged ? ' pinged everyone' : ' sent a message'),
                    default => $pinged ? 'Pinged you' : 'Sent you a message',
                },
                [
                    'type' => $pinged ? 'ping' : 'message',
                    'conversation_id' => $conversation->id,
                    'kind' => $conversation->kind->value,
                    ...$conversation->kind === ConversationKind::Direct ? ['handle' => $sender->handle] : [],
                ],
                $conversation->id,
            );

            try {
                foreach (Device::query()->where('user_id', $user->id)->whereNotNull('push_token_hash')
                    ->when($this->deviceIds !== null, fn (Builder $query): Builder => $query->whereKey($this->deviceIds))
                    ->orderBy('id')->get() as $device) {
                    if (! $devicePush->send($device, $devicePushMessage)) {
                        $failedDevices[] = $device->id;
                    }
                }
            } catch (Throwable $throwable) {
                // A push service that throws is misconfigured, and retrying won't help, so report it once and
                // leave the remaining phones. Browsers still get their push.
                report($throwable);
            }
        }

        $failed = [];

        foreach (PushSubscription::query()->where('user_id', $user->id)
            ->when($this->subscriptionIds !== null, fn (Builder $query): Builder => $query->whereKey($this->subscriptionIds))
            ->orderBy('id')->limit(PushSubscription::MAX_PER_USER)->get() as $subscription) {
            if (! $push->send($subscription)) {
                $failed[] = $subscription->id;
            }
        }

        if (($failed !== [] || $failedDevices !== []) && $this->attempts() < $this->tries) {
            // A follow-up starts with a fresh queue attempt count, so carry the remaining budget.
            dispatch(new self($this->messageId, $this->userId, $failed, $this->tries - $this->attempts(), $failedDevices)
                ->onConnection($this->connection)->onQueue($this->queue))->delay(now()->addSeconds(30));
        }
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
}
