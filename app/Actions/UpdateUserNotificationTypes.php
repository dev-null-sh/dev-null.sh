<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserNotificationTypes
{
    /**
     * Remembers which kinds of notifications the user wants to see, and refreshes the unread count wherever they are
     * signed in, since it only counts those kinds.
     */
    public function handle(User $user, string $type): void
    {
        if ($user->notification_types === $type) {
            return;
        }

        $user->update(['notification_types' => $type]);

        event(new UsersUpdated([$user->id], UserUpdate::Notifications));
    }
}
