<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;

final readonly class UpdateOrganizationLane
{
    public function handle(Organization $organization, ?int $lane, ?string $color): void
    {
        $organization->update(['preferred_lane' => $lane, 'lane_color' => $color]);
    }
}
