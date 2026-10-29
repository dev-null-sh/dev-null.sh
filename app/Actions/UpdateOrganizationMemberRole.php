<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Profile;
use App\Models\User;

final readonly class UpdateOrganizationMemberRole
{
    public function handle(Profile $organization, User $member, OrganizationRole $role): void
    {
        $organization->members()->updateExistingPivot($member->id, ['role' => $role]);
    }
}
