<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateEventAttendance
{
    /**
     * Two requests racing past validation both end with one attendance: the second insert is ignored.
     */
    public function handle(User $user, Event $event): void
    {
        DB::table('event_attendees')->insertOrIgnore([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
