<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Log;
use App\Models\User;

final readonly class UpdateUserLog
{
    /**
     * Remembering the log is not a profile change, so it leaves `updated_at`, and the link previews versioned by it, alone.
     */
    public function handle(User $user, Log $log): void
    {
        User::withoutTimestamps(fn (): bool => $user->update(['log' => $log]));
    }
}
