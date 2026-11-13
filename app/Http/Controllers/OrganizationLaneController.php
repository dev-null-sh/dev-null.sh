<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrganizationLane;
use App\Http\Requests\UpdateOrganizationLaneRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * An organization's color, in the Profile tab of its settings. Only its admins choose it.
 */
final readonly class OrganizationLaneController
{
    public function update(UpdateOrganizationLaneRequest $request, Organization $organization, UpdateOrganizationLane $action): RedirectResponse
    {
        $action->handle($organization, $request->lane(), $request->color());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Color saved.',
        ]);

        return back();
    }
}
