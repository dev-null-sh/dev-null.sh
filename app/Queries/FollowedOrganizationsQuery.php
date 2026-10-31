<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class FollowedOrganizationsQuery
{
    /**
     * The organizations a user follows, as their Following list shows them above the people: the newest follow first.
     * The viewer also gets whether they follow each one.
     *
     * @return BelongsToMany<Organization, User>
     */
    public function builder(User $user, User $viewer): BelongsToMany
    {
        return $user->followedOrganizations()
            ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($viewer->id)])
            ->latest('organization_follows.created_at')
            ->latest('organizations.id');
    }
}
