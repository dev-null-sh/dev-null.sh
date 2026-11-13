<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LivePlatform;
use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserLive
{
    public function handle(User $user, LivePlatform $platform, string $url, int $hours): void
    {
        $user->update([
            'live_platform' => $platform,
            'live_url' => $platform->driver()->stream($url)->url,
            'live_ends_at' => now()->addHours($hours),
        ]);

        event(new ProfilesUpdated([$user->id], changesLive: true));
        event(new UsersUpdated([$user->id], UserUpdate::Account));
        event(new UsersUpdated($user->followers()->pluck('users.id'), UserUpdate::Live));
    }
}
