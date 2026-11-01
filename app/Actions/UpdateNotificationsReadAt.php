<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class UpdateNotificationsReadAt
{
    public function handle(User $user, CarbonImmutable $through): CarbonImmutable
    {
        $readAt = CarbonImmutable::now()->startOfSecond();

        $user->notifications()
            ->relevant()
            ->unread()
            ->where('created_at', '<=', $through)
            ->update(['read_at' => $readAt]);

        return $readAt;
    }
}
