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

        UsersUpdated::dispatch([$followed->id], UserUpdate::Notifications);
        UsersUpdated::dispatch([$follower->id], UserUpdate::Account);
        UsersUpdated::dispatch([$follower->id], UserUpdate::Live);
        ProfilesUpdated::dispatch([$follower->id, $followed->id]);
    }
}
