<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrganizationPeopleVisibility;
use App\Http\Requests\UpdateOrganizationPeopleVisibilityRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Whether an organization's page shows its members to everyone, in the Profile tab of its settings. Only its admins
 * choose it.
 */
final readonly class OrganizationPeopleVisibilityController
{
    public function update(UpdateOrganizationPeopleVisibilityRequest $request, Organization $organization, UpdateOrganizationPeopleVisibility $action): RedirectResponse
    {
        $showsPeople = $request->showsPeople();

        $action->handle($organization, $showsPeople);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $showsPeople ? 'People are shown on the page.' : 'People are hidden from the page.',
        ]);

        return back();
    }
}
