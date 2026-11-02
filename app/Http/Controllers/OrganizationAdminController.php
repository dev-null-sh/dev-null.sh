<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationAdmin;
use App\Contracts\Services\GitHubProvider;
use App\Http\Requests\CreateOrganizationAdminRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Claiming an organization's page asks GitHub whether the user shows their membership of it publicly, so it needs no
 * permission beyond sign-in's, and no approval from the organization's owners.
 */
final readonly class OrganizationAdminController
{
    /**
     * Claiming is a form, so no other site can claim a page for the user.
     */
    public function store(CreateOrganizationAdminRequest $request, #[CurrentUser] User $user, Organization $organization, GitHubProvider $github, CreateOrganizationAdmin $action): RedirectResponse
    {
        $page = to_route('user.show', $organization->handle);

        $claimed = $this->claimed($organization);

        if ($claimed !== null) {
            return $this->failed($claimed, $page);
        }

        $handle = $organization->handle;

        if (! $github->isPublicMember($organization->profile->github_id, $user->github_id)) {
            return $this->failed(
                __('GitHub does not list you as a public member of @:handle. On GitHub, make your membership of @:handle public, then try again.', ['handle' => $handle]),
                $page,
                "https://github.com/orgs/{$handle}/people",
            );
        }

        if (! $action->handle($organization, $user)) {
            return $this->failed($this->claimed($organization) ?? __('Unable to claim this organization. Please try again.'), $page);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You are now an admin of @:handle.', ['handle' => $handle])]);

        return $page;
    }

    /**
     * Only the first claim makes an admin. Why another one fails, if the page was claimed.
     */
    private function claimed(Organization $organization): ?string
    {
        $admin = $organization->admins()->oldest('organization_members.created_at')->first();

        if ($admin === null) {
            return null;
        }

        return __('@:handle was already claimed by @:admin.', ['handle' => $organization->handle, 'admin' => $admin->handle]);
    }

    /**
     * The page says why the claim failed until the user leaves it, with where to fix it on GitHub when there's a way.
     */
    private function failed(string $message, RedirectResponse $redirect, ?string $githubUrl = null): RedirectResponse
    {
        Inertia::flash('claimFailed', ['message' => $message, 'githubUrl' => $githubUrl]);

        return $redirect;
    }
}
