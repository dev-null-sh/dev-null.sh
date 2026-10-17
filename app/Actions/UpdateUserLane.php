<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserLane
{
    public function handle(User $user, ?int $lane, ?string $color): void
    {
        $user->update(['preferred_lane' => $lane, 'lane_color' => $color]);
    }
}
