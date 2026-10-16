<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserOnboardedAt
{
    public function handle(User $user): void
    {
        $user->update(['onboarded_at' => $user->onboarded_at ?? now()]);
    }
}
