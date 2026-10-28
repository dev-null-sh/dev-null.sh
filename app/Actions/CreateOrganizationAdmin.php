<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Profile;
use App\Models\User;

final readonly class CreateOrganizationAdmin
{
    /**
     * Makes the user an admin of the organization's page, once GitHub confirmed they belong to it. Claiming again
     * changes nothing.
     */
    public function handle(Profile $organization, User $user): void
    {
        $organization->admins()->syncWithoutDetaching([$user->id]);
    }
}
