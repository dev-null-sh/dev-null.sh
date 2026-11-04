<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserNowPlaying
{
    public function handle(User $user, string $lastFmUsername): void
    {
        $user->update(['lastfm_username' => $lastFmUsername]);
    }
}
