<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Dto\DevicePushResult;
use App\Enums\ConversationKind;
use App\Enums\OrgEvent;
use App\Enums\PushPreference;
use App\Exceptions\DevicePushCredentialsRejected;
use App\Models\ConversationParticipant;
use App\Models\Device;
use App\Models\Message;
use App\Queries\RemotesPushRecipientQuery;
use App\Support\PushRetryDelay;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Throwable;

/**
 * Pushes a Remotes message or ping to the phones of one recipient, in a job of its own so a slow phone cannot use up
 * the time the browsers of the same message get. Shows who wrote, and only while the recipient keeps the switch for
 * the conversation's kind on.
 */
#[Timeout(210)]
final class SendRemotePhonePush implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds a phone that is offline can still get the push: the same five minutes as browsers, after which the
     * message is no longer new.
     */
    private const int PHONE_TTL = 300;

    private const int DEFAULT_TRIES = 3;

    private const int DEVICES_PER_JOB = 5;

    /**
     * @param  list<string>|null  $deviceIds
     */
    public function __construct(
        public readonly int $messageId,
        public readonly string $userId,
        public readonly ?array $deviceIds = null,
        public readonly int $tries = self::DEFAULT_TRIES,
    ) {
        //
    }

    public function handle(DevicePushProvider $push): void
    {
        $message = Message::query()->find($this->messageId);

        if (! $message instanceof Message) {
            return;
        }

        $participant = RemotesPushRecipientQuery::forPhones()->get($message, $this->userId);

        if (! $participant instanceof ConversationParticipant) {
            return;
        }

        if (! PushPreference::forConversation($message->conversation->kind)->isOnFor($participant->user)) {
            return;
        }

        $phonePush = $this->phonePush($message);
        $failed = [];
        $deferred = [];
        $retryAfterSeconds = null;

        $devices = Device::query()->where('user_id', $this->userId)->whereNotNull('push_token_hash')
            ->when($this->deviceIds !== null, fn (Builder $query): Builder => $query->whereKey($this->deviceIds))
            ->orderBy('id')->get();

        // Queue the rest before sending: even five OAuth exchanges and sends fit within this job's timeout.
        foreach ($devices->skip(self::DEVICES_PER_JOB)->chunk(self::DEVICES_PER_JOB) as $batch) {
            dispatch(new self($this->messageId, $this->userId, array_values($batch->map(fn (Device $device): string => $device->id)->all()), $this->tries - $this->attempts() + 1))
                ->onConnection($this->connection)->onQueue($this->queue);
        }

        foreach ($devices->take(self::DEVICES_PER_JOB) as $device) {
            try {
                $result = $push->send($device, $phonePush);
            } catch (DevicePushCredentialsRejected) {
                return;
            } catch (Throwable $throwable) {
                report($throwable);
                $result = DevicePushResult::retry();
            }

            if (! $result->retry) {
                continue;
            }

            if ($result->deferred) {
                $deferred[$result->retryAfterSeconds ?? 0][] = $device->id;

                continue;
            }

            $failed[] = $device->id;
            $retryAfterSeconds = max($retryAfterSeconds ?? 0, $result->retryAfterSeconds ?? 0);
        }

        foreach ($deferred as $seconds => $deviceIds) {
            dispatch(new self($this->messageId, $this->userId, $deviceIds, $this->tries - $this->attempts() + 1))
                ->onConnection($this->connection)->onQueue($this->queue)->delay(now()->addSeconds($seconds));
        }

        if ($failed === [] || $this->attempts() >= $this->tries) {
            return;
        }

        // A follow-up starts with a fresh queue attempt count, so carry the remaining budget.
        dispatch(new self($this->messageId, $this->userId, $failed, $this->tries - $this->attempts()))
            ->onConnection($this->connection)->onQueue($this->queue)->delay(now()->addSeconds($this->retryDelay($retryAfterSeconds)));
    }

    private function phonePush(Message $message): DevicePush
    {
        $conversation = $message->conversation;
        $sender = $message->user->profile;
        $name = $sender->name !== '' ? $sender->name : '@'.$sender->handle;
        $pinged = $message->event === OrgEvent::Ping;

        return new DevicePush(
            $conversation->kind === ConversationKind::Org ? ($conversation->name ?? 'Org') : $name,
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
            self::PHONE_TTL,
            $conversation->id,
        );
    }

    /**
     * What Google asked for, and otherwise twice as long each attempt.
     */
    private function retryDelay(?int $retryAfterSeconds): int
    {
        // A follow-up carries what is left of the budget, so the remainder says which attempt this is.
        return PushRetryDelay::secondsFor(self::DEFAULT_TRIES - $this->tries + $this->attempts(), $retryAfterSeconds);
    }
}
