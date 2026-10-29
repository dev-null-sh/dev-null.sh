<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Profile;

final readonly class UpdateOrganizationLane
{
    public function handle(Profile $organization, ?int $lane, ?string $color): void
    {
        $organization->update(['preferred_lane' => $lane, 'lane_color' => $color]);
    }
}
