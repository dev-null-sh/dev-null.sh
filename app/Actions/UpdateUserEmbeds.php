<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserEmbeds
{
    public function handle(User $user, bool $allowed): void
    {
        $user->update(['allows_embeds' => $allowed]);

        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
