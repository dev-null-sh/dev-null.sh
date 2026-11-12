<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserRemotes
{
    public function handle(User $user, bool $enabled): void
    {
        $user->update(['remotes_enabled' => $enabled]);

        event(new UsersUpdated([$user->id], UserUpdate::Account));
        event(new UsersUpdated([$user->id], UserUpdate::Inbox));

        if (! $enabled) {
            $user->pushSubscriptions()->delete();
        }
    }
}
