<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrganizationFollow;
use App\Actions\DeleteOrganizationFollow;
use App\Http\Requests\CreateApiOrganizationFollowRequest;
use App\Http\Requests\DeleteApiOrganizationFollowRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrganizationFollowController
{
    public function store(CreateApiOrganizationFollowRequest $request, #[CurrentUser] User $user, CreateOrganizationFollow $action): Response
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $action->handle($user, $organization);

        return response()->noContent();
    }

    public function destroy(DeleteApiOrganizationFollowRequest $request, #[CurrentUser] User $user, DeleteOrganizationFollow $action): Response
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $action->handle($user, $organization);

        return response()->noContent();
    }
}
