<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Profile;
use App\Models\User;

final readonly class DeleteOrganizationMember
{
    /**
     * Removes the member from the organization, whether an admin removed them or they left.
     */
    public function handle(Profile $organization, User $member): void
    {
        $organization->members()->detach($member->id);
    }
}
