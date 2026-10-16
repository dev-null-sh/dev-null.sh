<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\AppleTokens;

final readonly class UpdateUserAppleId
{
    public function __construct(private AppleTokens $appleTokens, private DeleteUserAppleId $deleteUserAppleId)
    {
        //
    }

    /**
     * Links an Apple ID to the user, as a second way into their account. It replaces any Apple ID they linked before,
     * which is unlinked the usual way. With the authorization code Apple handed the app, it also keeps a refresh token,
     * encrypted, to revoke once the Apple ID is unlinked. Linking the same Apple ID again keeps the token it has, unless
     * Apple hands over a new one.
     */
    public function handle(User $user, string $appleId, ?string $authorizationCode = null): void
    {
        $refreshToken = $authorizationCode === null ? null : $this->appleTokens->refreshToken($authorizationCode, $appleId);

        if ($user->apple_id !== null && $user->apple_id !== $appleId) {
            $this->deleteUserAppleId->handle($user);
        }

        $user->forceFill([
            'apple_id' => $appleId,
            'apple_refresh_token' => $refreshToken ?? $user->apple_refresh_token,
        ])->save();
    }
}
