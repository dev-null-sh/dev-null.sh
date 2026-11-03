<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class DeleteUserMute
{
    public function handle(User $user, User $muted): void
    {
        $user->muting()->detach($muted->id);

        UsersUpdated::dispatch([$user->id], UserUpdate::Notifications);
        UsersUpdated::dispatch([$user->id], UserUpdate::Account);
    }
}
