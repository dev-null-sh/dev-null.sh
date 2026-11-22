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
    /**
     * Follows the organization, as on the web. Following again changes nothing; an organization without a page yet is a
     * 404.
     */
    public function store(CreateApiOrganizationFollowRequest $request, #[CurrentUser] User $user, CreateOrganizationFollow $action): Response
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $action->handle($user, $organization);

        return response()->noContent();
    }

    /**
     * Stops following the organization. Unfollowing one the user doesn't follow changes nothing; an organization without
     * a page yet is a 404.
     */
    public function destroy(DeleteApiOrganizationFollowRequest $request, #[CurrentUser] User $user, DeleteOrganizationFollow $action): Response
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $action->handle($user, $organization);

        return response()->noContent();
    }
}
