<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Requests\ShowOrganizationProfileRequest;
use App\Models\Organization;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Profile tab of an organization's settings, like the user's, where its admins choose its color and whether its
 * page shows its people.
 */
final readonly class OrganizationProfileController
{
    public function edit(ShowOrganizationProfileRequest $request, Organization $organization): Response
    {
        return Inertia::render('organization-profile/edit', [
            'organization' => $organization,
            'role' => OrganizationRole::Admin,
            'showsPeople' => $organization->shows_people,
        ]);
    }
}
