<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class UpdateNotificationsReadAt
{
    public function handle(User $user): CarbonImmutable
    {
        $readAt = CarbonImmutable::now()->startOfSecond();

        $user->notifications()
            ->relevant()
            ->unread()
            ->update(['read_at' => $readAt]);

        return $readAt;
    }
}
