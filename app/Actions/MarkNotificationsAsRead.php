<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Notification;

final readonly class MarkNotificationsAsRead
{
    public function handle(Notification ...$notifications): void
    {
        Notification::query()
            ->whereKey(array_map(fn (Notification $notification): string => $notification->id, $notifications))
            ->unread()
            ->update(['read_at' => now()]);
    }
}
