<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserAutomaticTranslation
{
    public function handle(User $user, bool $enabled): void
    {
        $user->update(['automatic_translation' => $enabled]);

        UsersUpdated::dispatch([$user->id], UserUpdate::Account);
    }
}
