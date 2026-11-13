<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Notification;
use App\Models\UserTimeout;
use Illuminate\Support\Str;

final readonly class CreateUserTimeoutNotification
{
    public function handle(UserTimeout $timeout): void
    {
        $user = $timeout->user;

        if ($user === null) {
            return;
        }

        Notification::query()->create([
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $user->id,
            'actor_id' => $user->id,
            'type' => NotificationType::TimedOut,
            'timeout_id' => $timeout->id,
            'key' => 'timed_out:'.$timeout->id,
        ]);

        event(new UsersUpdated([$user->id], UserUpdate::Notifications));
    }
}
