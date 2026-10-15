<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

final readonly class CreateEventCancellation
{
    public function __construct(private CreateEventNotifications $createEventNotifications)
    {
        //
    }

    /**
     * Cancelling is final. Calendars that add the event again see STATUS:CANCELLED with a higher sequence.
     */
    public function handle(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $event->update([
                'cancelled_at' => now(),
                'sequence' => $event->sequence + 1,
            ]);

            $this->createEventNotifications->handle($event, NotificationType::EventCancelled);
        });
    }
}
