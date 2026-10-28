<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowOrganizationRequest;
use App\Models\Profile;
use App\Models\User;
use App\Queries\OrganizationMentionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationController
{
    /**
     * Organization pages are public, at the "@handle" of users, and show the organization's GitHub profile and the
     * commits that mention it.
     */
    public function show(ShowOrganizationRequest $request, #[CurrentUser] ?User $currentUser, OrganizationMentionsQuery $mentions): Response
    {
        $organization = $request->organization();
        assert($organization instanceof Profile);

        return Inertia::render('organization/show', [
            // The Mentions tab tells a restored commit belongs to it by the ID, whatever handle the commit mentions.
            'organization' => $organization->makeVisible('id'),
            'tab' => $request->tab(...),
            'commits' => Inertia::scroll(fn () => $mentions->builder($organization, $currentUser)
                ->cursorPaginate(20)
                ->appends(['tab' => $request->tab()])),
        ]);
    }
}
