<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserTimezone
{
    public function handle(User $user, ?string $timezone, bool $showLocalTime = false): void
    {
        $user->update([
            'timezone' => $timezone,
            'show_local_time' => $timezone !== null && $showLocalTime,
        ]);
    }
}
