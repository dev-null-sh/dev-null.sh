<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateNotificationsReadAt
{
    public function handle(User $user): void
    {
        $user->notifications()
            ->relevant()
            ->unread()
            ->update(['read_at' => now()]);
    }
}
