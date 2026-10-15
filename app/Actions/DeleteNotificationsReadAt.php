<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Notification;

final readonly class DeleteNotificationsReadAt
{
    public function handle(Notification ...$notifications): void
    {
        Notification::query()
            ->whereKey(array_map(fn (Notification $notification): string => $notification->id, $notifications))
            ->update(['read_at' => null]);
    }
}
