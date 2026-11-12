<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
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

        event(new UsersUpdated([$user->id], UserUpdate::Account));
        event(new UsersUpdated([$user->id], UserUpdate::Relationships));
        event(new ProfilesUpdated([$user->id, $organization->id], changesConnections: true));
    }
}
