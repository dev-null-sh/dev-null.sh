<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EventResponse;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;

final readonly class UpdateEventAttendance
{
    public function handle(User $user, Event $event, EventResponse $response): void
    {
        EventAttendee::query()->upsert(
            [['event_id' => $event->id, 'user_id' => $user->id, 'response' => $response->value, 'created_at' => now(), 'updated_at' => now()]],
            ['event_id', 'user_id'],
            ['response', 'updated_at'],
        );
    }
}
