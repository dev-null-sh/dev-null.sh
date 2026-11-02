<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrganizationRole;
use App\Http\Requests\ShowApiOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class OrganizationController
{
    /**
     * Organizations share the "@handle" pages with users, so the app asks for one when no user has the handle. A
     * signed request also gets whether its user follows it, whether they can claim it, as anyone signed in can before
     * it has an admin, and its settings on the web for its members, as its page on the web links them.
     */
    public function show(ShowApiOrganizationRequest $request, #[CurrentUser] ?User $user): OrganizationResource
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $organization->loadCount('followers')->load(['admins' => fn (BelongsToMany $admins): BelongsToMany => $admins->oldest('organization_members.created_at')]);

        return OrganizationResource::make($organization)->additional([
            'is_following' => $user?->followedOrganizations()->newPivotQuery()->where('organization_id', $organization->id)->exists() ?? false,
            'can_claim' => $user instanceof User && $organization->admins->isEmpty(),
            'settings_url' => match ($user instanceof User ? $organization->roleOf($user) : null) {
                OrganizationRole::Admin => route('organization-profile.edit', $organization),
                OrganizationRole::Member => route('organization-member.index', $organization),
                null => null,
            },
        ]);
    }
}
