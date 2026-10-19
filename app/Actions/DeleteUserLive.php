<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class DeleteUserLive
{
    public function handle(User $user): void
    {
        $user->update(['live_platform' => null, 'live_url' => null, 'live_ends_at' => null]);
    }
}
