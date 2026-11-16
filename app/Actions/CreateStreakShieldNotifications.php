<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Notification;
use App\Models\User;
use App\Queries\UserStreakQuery;
use Illuminate\Support\Str;

final readonly class CreateStreakShieldNotifications
{
    public function __construct(private UserStreakQuery $streaks) {}

    public function handle(User $user): void
    {
        $since = $user->streak_shield_notifications_started_at->toDateString();
        $created = 0;

        foreach ($this->streaks->events($user) as $event) {
            if ($event['day'] < $since) {
                continue;
            }

            $balance = $event['balance'];
            $day = $event['day'];
            $message = match ($event['type']) {
                NotificationType::StreakShieldEarned => "Earned on {$day}. {$balance}/2 shields available.",
                NotificationType::StreakShieldUsed => "Protected {$day}. {$balance}/2 shields remaining.".($balance === 0 ? ' Your next missed day will end your streak unless you earn another shield.' : ''),
                default => "Your streak ended on {$day} because that day was not protected. {$balance}/2 shields saved. Commit again to start a new streak.",
            };

            $created += Notification::query()->insertOrIgnore([
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $user->id,
                'actor_id' => $user->id,
                'type' => $event['type']->value,
                'key' => $event['type']->value.':'.$day,
                'reason' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($created > 0) {
            event(new UsersUpdated([$user->id], UserUpdate::Notifications));
        }
    }
}
