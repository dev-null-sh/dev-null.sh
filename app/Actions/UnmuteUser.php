<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UnmuteUser
{
    public function handle(User $user, User $muted): void
    {
        $user->muting()->detach($muted->id);
    }
}
