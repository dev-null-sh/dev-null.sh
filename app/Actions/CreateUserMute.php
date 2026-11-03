<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class CreateUserMute
{
    /**
     * Muting is silent: the muted user is not notified, and follows in either direction stay as they are.
     */
    public function handle(User $user, User $muted): void
    {
        $now = now();

        $user->muting()->newPivotStatement()->insertOrIgnore([
            'muter_id' => $user->id,
            'muted_id' => $muted->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        event(new UsersUpdated([$user->id], UserUpdate::Notifications));
        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
