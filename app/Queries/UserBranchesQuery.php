<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\BranchRole;
use App\Models\User;

final readonly class UserBranchesQuery
{
    /**
     * Branches the user maintains or contributes to, maintainers first, then by name.
     *
     * @return list<array{
     *     name: string,
     *     role: string,
     *     description: string|null,
     *     is_verified: bool,
     *     verified_domain: string|null,
     *     brand_color: string|null,
     * }>
     */
    public function get(User $user): array
    {
        return $user->branchMemberships()
            ->with('branch')
            ->get()
            ->sortBy(fn ($membership): array => [
                $membership->role === BranchRole::Maintainer ? 0 : 1,
                $membership->branch->name,
            ])
            ->values()
            ->map(fn ($membership): array => [
                'name' => $membership->branch->name,
                'role' => $membership->role->value,
                'description' => $membership->branch->description,
                'is_verified' => $membership->branch->isVerified(),
                'verified_domain' => $membership->branch->isVerified()
                    ? $membership->branch->verified_domain
                    : null,
                'brand_color' => $membership->branch->isVerified()
                    ? $membership->branch->brand_color
                    : null,
            ])
            ->all();
    }
}
