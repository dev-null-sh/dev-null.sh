<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrganizationEmbeds;
use App\Http\Requests\UpdateOrganizationEmbedsRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Whether others may embed the organization's commits, in the Profile tab of its settings. Only its admins decide.
 */
final readonly class OrganizationEmbedsController
{
    public function update(UpdateOrganizationEmbedsRequest $request, Organization $organization, UpdateOrganizationEmbeds $action): RedirectResponse
    {
        $action->handle($organization, $request->boolean('enabled'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Embeds preference saved.',
        ]);

        return back();
    }
}
