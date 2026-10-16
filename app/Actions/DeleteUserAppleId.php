<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class DeleteUserAppleId
{
    /**
     * Unlinks the user's Apple ID, so Sign in with Apple no longer finds their account. Devices it approved stay
     * approved until they are revoked.
     */
    public function handle(User $user): void
    {
        $user->forceFill(['apple_id' => null])->save();
    }
}
