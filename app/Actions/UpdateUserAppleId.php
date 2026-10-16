<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserAppleId
{
    /**
     * Links an Apple ID to the user, as a second way into their account. It replaces any Apple ID they linked before.
     */
    public function handle(User $user, string $appleId): void
    {
        $user->forceFill(['apple_id' => $appleId])->save();
    }
}
