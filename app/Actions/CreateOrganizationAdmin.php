<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrganizationAdmin
{
    /**
     * Makes the user the admin of the organization's page, once GitHub confirmed they belong to it, unless someone
     * claimed it first. The organization is locked while it's checked, as two members coming back from GitHub at once
     * can both pass the request's check: the second claim then just doesn't happen.
     */
    public function handle(Organization $organization, User $user): bool
    {
        return DB::transaction(function () use ($organization, $user): bool {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            if ($organization->admins()->exists()) {
                return false;
            }

            // A member is left without an admin when the last one erased their account...
            $organization->members()->syncWithoutDetaching([$user->id => ['role' => OrganizationRole::Admin]]);

            return true;
        });
    }
}
