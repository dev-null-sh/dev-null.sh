<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class OrganizationMembersQuery
{
    /**
     * The organization's members as its settings list them, first joined first, each with its role and when it joined.
     *
     * @return Collection<int, User>
     */
    public function get(Organization $organization): Collection
    {
        return $this->builder($organization)->get()->makeHidden('pivot');
    }

    /**
     * One member, read like the list reads them. A member removed meanwhile is a 404.
     */
    public function find(Organization $organization, User $member): User
    {
        return $this->builder($organization)->whereKey($member->id)->firstOrFail()->makeHidden('pivot');
    }

    /**
     * @return BelongsToMany<User, Organization>
     */
    private function builder(Organization $organization): BelongsToMany
    {
        return $organization->members()
            ->select(['users.*', 'organization_members.role', 'organization_members.created_at as joined_at'])
            ->withCasts(['joined_at' => 'datetime'])
            ->oldest('organization_members.created_at');
    }
}
