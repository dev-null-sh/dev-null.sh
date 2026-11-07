<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class DeleteUserPlaylist
{
    public function handle(User $user): void
    {
        $user->update(['playlist_url' => null]);

        event(new ProfilesUpdated([$user->id]));
        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
