<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Organization;
use App\Models\User;

final readonly class DeleteOrganizationFollow
{
    public function handle(User $user, Organization $organization): void
    {
        $user->followedOrganizations()->detach($organization->id);

        UsersUpdated::dispatch([$user->id], UserUpdate::Account);
    }
}
