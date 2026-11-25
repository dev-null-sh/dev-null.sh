<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Dto\DevicePushResult;
use App\Enums\NotificationType;
use App\Enums\PushPreference;
use App\Exceptions\DevicePushCredentialsRejected;
use App\Models\Device;
use App\Models\Notification;
use App\Support\PushRetryDelay;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Throwable;

#[Timeout(210)]
final class SendNotificationPush implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds a phone that is offline can still get the push, so one that comes back the next day isn't handed a
     * follow or a reply from last week.
     */
    private const int PHONE_TTL = 86400;

    private const int DEFAULT_TRIES = 3;

    private const int DEVICES_PER_JOB = 5;

    /**
     * @param  list<string>|null  $deviceIds
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly ?array $deviceIds = null,
        public readonly int $tries = self::DEFAULT_TRIES,
    ) {
        //
    }

    /**
     * Queues a push for each of the notifications that exist, so the ids of rows an insert ignored push nobody, and
     * only to people with a phone to push to.
     *
     * @param  list<string>  $notificationIds
     */
    public static function dispatchFor(array $notificationIds): void
    {
        if ($notificationIds === []) {
            return;
        }

        foreach (Notification::query()->whereKey($notificationIds)
            ->whereHas('recipient.devices', fn (Builder $query): Builder => $query->whereNotNull('push_token_hash'))
            ->get(['id']) as $notification) {
            dispatch(new self($notification->id))->delay(now()->addSeconds(10))->afterCommit();
        }
    }

    /**
     * Pushes to every phone of the recipient, unless by now the notification was read or the app would hide it (the
     * follow was undone, the commit is gone, or a mute or block came between them), or the recipient turned its kind
     * off.
     */
    public function handle(DevicePushProvider $push): void
    {
        $notification = Notification::query()->whereKey($this->notificationId)->relevant()->unread()
            ->where('created_at', '>', now()->subSeconds(self::PHONE_TTL))
            ->with(['recipient', 'actor', 'organization'])->first();

        if (! $notification instanceof Notification || PushPreference::forNotification($notification->type)?->isOnFor($notification->recipient) !== true) {
            return;
        }

        // The actor is the commit's author, so an organization's reply or mention names the organization, never the
        // member who posted it.
        $actor = $notification->actor;
        $organization = $notification->organization;
        $message = new DevicePush(
            $actor->name !== '' ? $actor->name : '@'.$actor->handle,
            match ($notification->type) {
                NotificationType::Followed => 'Followed you',
                NotificationType::Replied => $organization === null ? 'Replied to your commit' : "Replied to {$organization->name}'s commit",
                default => ($organization === null ? 'Mentioned you' : 'Mentioned '.$organization->name).' in a commit',
            },
            array_filter([
                'type' => $notification->type->value,
                'notification_id' => $notification->id,
                'commit_id' => $notification->commit_id,
                'handle' => $actor->handle,
            ], fn (?string $value): bool => $value !== null),
            self::PHONE_TTL,
        );
        $failed = [];
        $deferred = [];
        $retryAfterSeconds = null;

        $devices = Device::query()->where('user_id', $notification->recipient_id)->whereNotNull('push_token_hash')
            ->when($this->deviceIds !== null, fn (Builder $query): Builder => $query->whereKey($this->deviceIds))
            ->orderBy('id')->get();

        // Queue the rest before sending: even five OAuth exchanges and sends fit within this job's timeout.
        foreach ($devices->skip(self::DEVICES_PER_JOB)->chunk(self::DEVICES_PER_JOB) as $batch) {
            dispatch(new self($this->notificationId, array_values($batch->map(fn (Device $device): string => $device->id)->all()), $this->tries - $this->attempts() + 1))
                ->onConnection($this->connection)->onQueue($this->queue);
        }

        foreach ($devices->take(self::DEVICES_PER_JOB) as $device) {
            try {
                $result = $push->send($device, $message);
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
            dispatch(new self($this->notificationId, $deviceIds, $this->tries - $this->attempts() + 1))
                ->onConnection($this->connection)->onQueue($this->queue)->delay(now()->addSeconds($seconds));
        }

        if ($failed === [] || $this->attempts() >= $this->tries) {
            return;
        }

        // A follow-up starts with a fresh queue attempt count, so carry the remaining budget.
        dispatch(new self($this->notificationId, $failed, $this->tries - $this->attempts())
            ->onConnection($this->connection)->onQueue($this->queue))->delay(now()->addSeconds($this->retryDelay($retryAfterSeconds)));
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
