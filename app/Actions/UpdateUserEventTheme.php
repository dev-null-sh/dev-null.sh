<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserEventTheme
{
    public function handle(User $user, string $choice): void
    {
        $user->update(['event_theme' => $choice]);

        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
