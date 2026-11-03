<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class DeleteUserBlock
{
    public function handle(User $user, User $blocked): void
    {
        $user->blocking()->detach($blocked->id);

        foreach ([UserUpdate::Notifications, UserUpdate::Account, UserUpdate::Inbox] as $update) {
            event(new UsersUpdated([$user->id, $blocked->id], $update));
        }
    }
}
