<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;

final readonly class CreateOrganizationFollow
{
    /**
     * Following again changes nothing, and keeps when the user first followed.
     */
    public function handle(User $user, Organization $organization): void
    {
        $user->followedOrganizations()->syncWithoutDetaching([$organization->id]);
    }
}
