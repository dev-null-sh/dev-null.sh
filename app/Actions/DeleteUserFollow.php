<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class DeleteUserFollow
{
    public function handle(User $follower, User $followed): void
    {
        $follower->following()->detach($followed->id);

        event(new UsersUpdated([$followed->id], UserUpdate::Notifications));
        event(new UsersUpdated([$follower->id, $followed->id], UserUpdate::Account));
        event(new UsersUpdated([$follower->id], UserUpdate::Relationships));
        event(new UsersUpdated([$follower->id], UserUpdate::Live));
        event(new ProfilesUpdated([$follower->id, $followed->id], changesConnections: true));
    }
}
