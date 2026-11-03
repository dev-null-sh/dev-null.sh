<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class DeleteUserLive
{
    public function handle(User $user): void
    {
        $user->update(['live_platform' => null, 'live_url' => null, 'live_ends_at' => null]);

        ProfilesUpdated::dispatch([$user->id]);
        UsersUpdated::dispatch($user->followers()->pluck('users.id'), UserUpdate::Live);
    }
}
