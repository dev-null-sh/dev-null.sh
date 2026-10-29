<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Profile;
use App\Models\User;

final readonly class CreateOrganizationMember
{
    /**
     * Adds the user to the organization as a member, whom an admin can then make an admin.
     */
    public function handle(Profile $organization, User $user): void
    {
        $organization->members()->attach($user->id, ['role' => OrganizationRole::Member]);
    }
}
