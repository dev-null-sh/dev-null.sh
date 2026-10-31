<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\OrganizationMustHaveAnAdmin;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteOrganizationMember
{
    /**
     * Removes the member from the organization, whether an admin removed them or they left. The organization is locked
     * while its last admin is checked again, as two admins removing each other at once can both pass the request's
     * check: the second removal then throws instead.
     *
     * @throws OrganizationMustHaveAnAdmin
     */
    public function handle(Organization $organization, User $member): void
    {
        DB::transaction(function () use ($organization, $member): void {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            if ($organization->isAdmin($member) && $organization->admins()->whereKeyNot($member->id)->doesntExist()) {
                throw new OrganizationMustHaveAnAdmin($organization);
            }

            $organization->members()->detach($member->id);
        });
    }
}
