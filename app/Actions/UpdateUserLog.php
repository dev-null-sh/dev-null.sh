<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Log;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserLog
{
    public function handle(User $user, Log $log): void
    {
        $user->update(['log' => $log]);

        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
