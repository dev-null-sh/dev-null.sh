<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Notification;
use App\Queries\NotificationBuilder;

final readonly class UpdateNotificationsReadAt
{
    /**
     * Reading a notification reads the whole group it is shown in.
     */
    public function handle(Notification ...$notifications): void
    {
        if ($notifications === []) {
            return;
        }

        Notification::query()
            ->where(function (NotificationBuilder $query) use ($notifications): void {
                foreach ($notifications as $notification) {
                    $query->orWhere(fn (NotificationBuilder $query): NotificationBuilder => $query->inGroupOf($notification));
                }
            })
            ->unread()
            ->update(['read_at' => now()]);
    }
}
