<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Event;
use App\Models\User;

final readonly class DeleteEventAttendance
{
    public function handle(User $user, Event $event): void
    {
        $event->attendees()->where('user_id', $user->id)->delete();
    }
}
