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
use Illuminate\Http\RedirectResponse;

final readonly class OrganizationFollowController
{
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
