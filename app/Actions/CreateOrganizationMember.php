<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Organization;
use App\Models\User;

final readonly class CreateOrganizationMember
{
    /**
     * Adds the user to the organization as a member, whom an admin can then make an admin.
     */
    public function handle(Organization $organization, User $user): void
    {
        $organization->members()->attach($user->id, ['role' => OrganizationRole::Member]);

        UsersUpdated::dispatch([$user->id, ...$organization->members()->pluck('users.id')], UserUpdate::Account);
    }
}
