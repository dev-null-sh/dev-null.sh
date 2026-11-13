<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationFollow;
use App\Actions\DeleteOrganizationFollow;
use App\Http\Requests\CreateOrganizationFollowRequest;
use App\Http\Requests\DeleteOrganizationFollowRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final readonly class OrganizationFollowController
{
    /**
     * Whether the user follows the organization, which commits from it don't carry, for the commit menu to ask once it opens.
     */
    public function show(#[CurrentUser] User $user, Organization $organization): JsonResponse
    {
        return response()->json([
            'isFollowing' => $user->followedOrganizations()->newPivotQuery()->where('organization_id', $organization->id)->exists(),
        ]);
    }

    public function store(CreateOrganizationFollowRequest $request, #[CurrentUser] User $user, Organization $organization, CreateOrganizationFollow $action): RedirectResponse
    {
        $action->handle($user, $organization);

        return back();
    }

    public function destroy(DeleteOrganizationFollowRequest $request, #[CurrentUser] User $user, Organization $organization, DeleteOrganizationFollow $action): RedirectResponse
    {
        $action->handle($user, $organization);

        return back();
    }
}
