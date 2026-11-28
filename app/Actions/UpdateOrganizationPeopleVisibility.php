<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\ProfilesUpdated;
use App\Models\Organization;

final readonly class UpdateOrganizationPeopleVisibility
{
    public function handle(Organization $organization, bool $showsPeople): void
    {
        $organization->update(['shows_people' => $showsPeople]);

        event(new ProfilesUpdated([$organization->id]));
    }
}
