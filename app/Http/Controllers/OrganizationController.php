<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowOrganizationRequest;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationController
{
    /**
     * Organization pages are public, at the "@handle" of users, and show the organization's GitHub profile.
     */
    public function show(ShowOrganizationRequest $request): Response
    {
        return Inertia::render('organization/show', [
            'organization' => $request->organization(),
        ]);
    }
}
