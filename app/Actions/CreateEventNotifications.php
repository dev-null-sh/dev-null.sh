<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateEventNotifications
{
    /**
     * Tells everyone going, except the host, that the event changed or was cancelled. Every change bumps the
     * sequence, so the key notifies once per change.
     */
    public function handle(Event $event, NotificationType $type): void
    {
        $commit = $event->commit;

        if ($commit->status !== CommitStatus::Approved) {
            return;
        }

        $notifications = $event->attendees()
            ->whereKeyNot($commit->user_id)
            ->get(['users.id'])
            ->map(fn (User $recipient): array => [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $recipient->id,
                'actor_id' => $commit->user_id,
                'type' => $type->value,
                'commit_id' => $commit->id,
                'key' => "{$type->value}:{$event->id}:{$event->sequence}",
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        Notification::query()->insertOrIgnore($notifications);
    }
}
