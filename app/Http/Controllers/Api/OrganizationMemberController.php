<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrganizationMember;
use App\Actions\DeleteOrganizationMember;
use App\Actions\UpdateOrganizationMemberRole;
use App\Enums\OrganizationRole;
use App\Exceptions\OrganizationMustHaveAnAdmin;
use App\Http\Requests\CreateApiOrganizationMemberRequest;
use App\Http\Requests\DeleteApiOrganizationMemberRequest;
use App\Http\Requests\ListApiOrganizationMembersRequest;
use App\Http\Requests\UpdateApiOrganizationMemberRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\Organization;
use App\Models\User;
use App\Queries\OrganizationMembersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * An organization's members, as its settings manage them on the web.
 */
final readonly class OrganizationMemberController
{
    public function index(ListApiOrganizationMembersRequest $request, OrganizationMembersQuery $members): AnonymousResourceCollection
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        return OrganizationMemberResource::collection($members->get($organization));
    }

    public function store(CreateApiOrganizationMemberRequest $request, CreateOrganizationMember $action, OrganizationMembersQuery $members): JsonResponse
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $member = $request->member();
        assert($member instanceof User);

        $action->handle($organization, $member);

        return new OrganizationMemberResource($members->find($organization, $member))->response()->setStatusCode(201);
    }

    public function update(UpdateApiOrganizationMemberRequest $request, string $handle, User $member, UpdateOrganizationMemberRole $action, OrganizationMembersQuery $members): OrganizationMemberResource|JsonResponse
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $role = $request->role();
        assert($role instanceof OrganizationRole);

        try {
            $action->handle($organization, $member, $role);
        } catch (OrganizationMustHaveAnAdmin $organizationMustHaveAnAdmin) {
            return $this->refused('role', $organizationMustHaveAnAdmin);
        }

        return new OrganizationMemberResource($members->find($organization, $member));
    }

    public function destroy(DeleteApiOrganizationMemberRequest $request, string $handle, User $member, DeleteOrganizationMember $action): Response|JsonResponse
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        try {
            $action->handle($organization, $member);
        } catch (OrganizationMustHaveAnAdmin $organizationMustHaveAnAdmin) {
            return $this->refused('member', $organizationMustHaveAnAdmin);
        }

        return response()->noContent();
    }

    /**
     * Another admin was made a member or left meanwhile: the same 422 the form request gives the last admin's change.
     */
    private function refused(string $field, OrganizationMustHaveAnAdmin $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => [$field => [$exception->getMessage()]],
        ], 422);
    }
}
