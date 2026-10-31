<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Exceptions\OrganizationMustHaveAnAdmin;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrganizationMemberRole
{
    /**
     * Makes the member an admin or a member. The organization is locked while its last admin is checked again, as two
     * admins making each other members at once can both pass the request's check: the second change then throws
     * instead.
     *
     * @throws OrganizationMustHaveAnAdmin
     */
    public function handle(Organization $organization, User $member, OrganizationRole $role): void
    {
        DB::transaction(function () use ($organization, $member, $role): void {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            if ($role === OrganizationRole::Member && $organization->admins()->whereKeyNot($member->id)->doesntExist()) {
                throw new OrganizationMustHaveAnAdmin($organization);
            }

            $organization->members()->updateExistingPivot($member->id, ['role' => $role]);
        });
    }
}
