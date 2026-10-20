<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UnixRank;
use App\Models\Notification;
use App\Models\User;
use App\Queries\UserAchievementsQuery;
use Illuminate\Support\Str;

final readonly class CreateLevelNotification
{
    public function __construct(private UserAchievementsQuery $achievements)
    {
        //
    }

    /**
     * The level is the one everyone sees on the profile, so a pending commit that ends up rejected never levels anyone
     * up. Each level notifies once, ever: losing XP and earning it back does not bring the same notification again.
     * Someone who reaches a level before ever being checked hears about that level only, not every one on the way.
     */
    public function handle(User $user): void
    {
        $level = $this->achievements->get($user, null)['level'];

        if ($level <= UnixRank::User->level()) {
            return;
        }

        Notification::query()->insertOrIgnore([
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $user->id,
            'actor_id' => $user->id,
            'type' => NotificationType::LeveledUp->value,
            'commit_id' => null,
            'level' => $level,
            'key' => 'leveled_up:'.$level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
