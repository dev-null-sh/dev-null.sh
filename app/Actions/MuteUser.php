<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use InvalidArgumentException;

final readonly class MuteUser
{
    /**
     * Muting is silent: the muted user is not notified, and follows in either direction stay as they are.
     */
    public function handle(User $user, User $muted): void
    {
        throw_if($user->is($muted), InvalidArgumentException::class, 'Users cannot mute themselves.');

        $user->muting()->syncWithoutDetaching([$muted->id]);
    }
}
