<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserPasteisMode
{
    public function handle(User $user, bool $enabled): void
    {
        $user->update(['pasteis_mode' => $enabled]);

        UsersUpdated::dispatch([$user->id], UserUpdate::Account);
    }
}
