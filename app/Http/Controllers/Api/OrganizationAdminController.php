<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrganizationAdmin;
use App\Http\Requests\CreateApiOrganizationAdminRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class OrganizationAdminController
{
    /**
     * Claims the organization's page, as Claim does on the web. Someone else claiming it in between leaves it theirs.
     */
    public function store(CreateApiOrganizationAdminRequest $request, #[CurrentUser] User $user, CreateOrganizationAdmin $action): JsonResponse|Response
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        return $action->handle($organization, $user)
            ? response()->noContent()
            : response()->json(['message' => __('Unable to claim this organization. Please try again.')], 409);
    }
}
