<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserPlaylist
{
    public function handle(User $user, string $url): void
    {
        $user->update(['playlist_url' => $url]);

        event(new ProfilesUpdated([$user->id]));
        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
