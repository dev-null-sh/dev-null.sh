<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrganizationLane;
use App\Enums\OrganizationRole;
use App\Http\Requests\ShowOrganizationLaneRequest;
use App\Http\Requests\UpdateOrganizationLaneRequest;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An organization's color, in its settings. Only its admins choose it.
 */
final readonly class OrganizationLaneController
{
    public function edit(ShowOrganizationLaneRequest $request, Profile $organization): Response
    {
        return Inertia::render('organization-lane/edit', [
            'organization' => $organization->makeVisible('id'),
            'role' => OrganizationRole::Admin,
            'lane' => $organization->lane(),
        ]);
    }

    public function update(UpdateOrganizationLaneRequest $request, Profile $organization, UpdateOrganizationLane $action): RedirectResponse
    {
        $action->handle($organization, $request->lane(), $request->color());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Color saved.',
        ]);

        return back();
    }
}
