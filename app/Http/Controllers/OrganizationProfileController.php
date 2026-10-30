<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Requests\ShowOrganizationProfileRequest;
use App\Models\Profile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Profile tab of an organization's settings, like the user's, where its admins choose its color.
 */
final readonly class OrganizationProfileController
{
    public function edit(ShowOrganizationProfileRequest $request, Profile $organization): Response
    {
        return Inertia::render('organization-profile/edit', [
            'organization' => $organization->makeVisible('id'),
            'role' => OrganizationRole::Admin,
            'lane' => $organization->lane(),
        ]);
    }
}
