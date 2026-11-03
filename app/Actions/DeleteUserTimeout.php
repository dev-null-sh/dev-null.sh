<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class DeleteUserTimeout
{
    /**
     * Ends the user's timeouts early instead of erasing them, so they still count.
     */
    public function handle(User $user): void
    {
        $user->timeouts()->where('ends_at', '>', now())->update(['ends_at' => now()]);

        UsersUpdated::dispatch([$user->id], UserUpdate::Account);
    }
}
