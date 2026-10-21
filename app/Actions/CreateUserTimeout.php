<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ModerationReason;
use App\Enums\TimeoutDuration;
use App\Models\User;
use App\Models\UserTimeout;

final readonly class CreateUserTimeout
{
    /**
     * Every timeout is kept once it's over, so moderators can see how many the user has had.
     */
    public function handle(User $user, ModerationReason $reason, TimeoutDuration $duration): UserTimeout
    {
        return $user->timeouts()->create([
            'reason' => $reason,
            'ends_at' => now()->addMinutes($duration->value),
        ]);
    }
}
