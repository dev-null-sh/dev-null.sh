<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationMember;
use App\Actions\DeleteOrganizationMember;
use App\Actions\UpdateOrganizationMemberRole;
use App\Enums\OrganizationRole;
use App\Exceptions\OrganizationMustHaveAnAdmin;
use App\Http\Requests\CreateOrganizationMemberRequest;
use App\Http\Requests\DeleteOrganizationMemberRequest;
use App\Http\Requests\ListOrganizationMembersRequest;
use App\Http\Requests\UpdateOrganizationMemberRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organization's members, in its settings. GitHub only lets the first admin in: admins manage everyone else here.
 */
final readonly class OrganizationMemberController
{
    /**
     * Every member sees who the members are, first joined first. Only admins can change them.
     */
    public function index(ListOrganizationMembersRequest $request, #[CurrentUser] User $user, Profile $organization): Response
    {
        return Inertia::render('organization-member/index', [
            'organization' => $organization->makeVisible('id'),
            'role' => $organization->roleOf($user),
            'members' => $organization->members()
                ->oldest('organization_members.created_at')
                ->withCasts(['joined_at' => 'datetime'])
                ->get(['users.*', 'organization_members.role', 'organization_members.created_at as joined_at'])
                ->makeHidden('pivot'),
        ]);
    }

    public function store(CreateOrganizationMemberRequest $request, Profile $organization, CreateOrganizationMember $action): RedirectResponse
    {
        $member = $request->member();
        assert($member instanceof User);

        $action->handle($organization, $member);

        return back();
    }

    public function update(UpdateOrganizationMemberRequest $request, Profile $organization, User $member, UpdateOrganizationMemberRole $action): RedirectResponse
    {
        $role = $request->role();
        assert($role instanceof OrganizationRole);

        try {
            $action->handle($organization, $member, $role);
        } catch (OrganizationMustHaveAnAdmin $organizationMustHaveAnAdmin) {
            return $this->refused($organizationMustHaveAnAdmin);
        }

        return back();
    }

    /**
     * Leaving takes the user to the organization's page, as its settings are for members only.
     */
    public function destroy(DeleteOrganizationMemberRequest $request, #[CurrentUser] User $user, Profile $organization, User $member, DeleteOrganizationMember $action): RedirectResponse
    {
        try {
            $action->handle($organization, $member);
        } catch (OrganizationMustHaveAnAdmin $organizationMustHaveAnAdmin) {
            return $this->refused($organizationMustHaveAnAdmin);
        }

        if ($user->is($member)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('You left @:handle.', ['handle' => $organization->handle])]);

            return to_route('user.show', $organization->handle);
        }

        return back();
    }

    /**
     * Another admin was made a member or left meanwhile, so the change would have left no admin.
     */
    private function refused(OrganizationMustHaveAnAdmin $exception): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

        return back();
    }
}
