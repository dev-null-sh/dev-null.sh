<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EventResponse;
use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Notification;
use Illuminate\Support\Str;

final readonly class CreateEventCancellationNotifications
{
    /**
     * Only the people going hear about it: an interested answer is private, so nothing acts on it. The host is the actor,
     * and the announcement commit is the subject, so the notification goes once the commit does.
     */
    public function handle(Event $event): void
    {
        $hostId = $event->commit?->user_id;

        Notification::query()->insertOrIgnore($event->attendees()
            ->where('response', EventResponse::Going)
            ->get()
            ->map(fn (EventAttendee $attendee): array => [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $attendee->user_id,
                'actor_id' => $hostId,
                'type' => NotificationType::EventCancelled->value,
                'commit_id' => $event->commit_id,
                'key' => 'event_cancelled:'.$event->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all());
    }
}
