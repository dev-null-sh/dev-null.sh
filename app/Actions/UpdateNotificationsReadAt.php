<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
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

        UsersUpdated::dispatch([$user->id], UserUpdate::Notifications);

        return $readAt;
    }
}
