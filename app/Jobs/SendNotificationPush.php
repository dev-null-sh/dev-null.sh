<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Enums\NotificationType;
use App\Enums\PushPreference;
use App\Models\Device;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;

#[Timeout(210)]
final class SendNotificationPush implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>|null  $deviceIds
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly ?array $deviceIds = null,
        public readonly int $tries = 3,
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
            ->with(['recipient', 'actor', 'organization'])->first();

        if (! $notification instanceof Notification || PushPreference::forNotification($notification->type)?->isOnFor($notification->recipient) !== true) {
            return;
        }

        $actor = $notification->actor;
        $organization = $notification->organization;
        $message = new DevicePush(
            $actor->name !== '' ? $actor->name : '@'.$actor->handle,
            match ($notification->type) {
                NotificationType::Followed => 'Followed you',
                NotificationType::Replied => $organization === null ? 'Replied to your commit' : "Replied to {$organization->name}'s commit",
                default => $organization === null ? 'Mentioned you' : 'Mentioned '.$organization->name,
            },
            array_filter([
                'type' => $notification->type->value,
                'notification_id' => $notification->id,
                'commit_id' => $notification->commit_id,
                'handle' => $actor->handle,
            ], fn (?string $value): bool => $value !== null),
        );
        $failed = [];

        foreach (Device::query()->where('user_id', $notification->recipient_id)->whereNotNull('push_token_hash')
            ->when($this->deviceIds !== null, fn (Builder $query): Builder => $query->whereKey($this->deviceIds))
            ->orderBy('id')->get() as $device) {
            if (! $push->send($device, $message)) {
                $failed[] = $device->id;
            }
        }

        if ($failed !== [] && $this->attempts() < $this->tries) {
            // A follow-up starts with a fresh queue attempt count, so carry the remaining budget.
            dispatch(new self($this->notificationId, $failed, $this->tries - $this->attempts())
                ->onConnection($this->connection)->onQueue($this->queue))->delay(now()->addSeconds(30));
        }
    }
}
