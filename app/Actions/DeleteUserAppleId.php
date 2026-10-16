<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\RevokeAppleToken;
use App\Models\User;

final readonly class DeleteUserAppleId
{
    /**
     * Unlinks the user's Apple ID, so Sign in with Apple no longer finds their account, and has Apple revoke its refresh
     * token, if one was kept. Devices it approved stay approved until they are revoked.
     */
    public function handle(User $user): void
    {
        $refreshToken = $user->apple_refresh_token;

        $user->forceFill(['apple_id' => null, 'apple_refresh_token' => null])->save();

        if ($refreshToken !== null) {
            dispatch(new RevokeAppleToken($refreshToken));
        }
    }
}
