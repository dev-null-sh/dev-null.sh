<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrganizationLane;
use App\Http\Requests\UpdateOrganizationLaneRequest;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class OrganizationLaneController
{
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
