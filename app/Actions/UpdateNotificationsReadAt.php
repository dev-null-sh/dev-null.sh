<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class UpdateNotificationsReadAt
{
    public function handle(User $user, string $type): CarbonImmutable
    {
        $readAt = CarbonImmutable::now()->startOfSecond();

        $user->notifications()
            ->relevant()
            ->unread()
            ->ofType($type)
            ->update(['read_at' => $readAt]);

        return $readAt;
    }
}
