<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class DeleteUserNowPlaying
{
    public function handle(User $user): void
    {
        $user->update(['lastfm_username' => null]);
    }
}
