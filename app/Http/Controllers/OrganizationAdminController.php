<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationAdmin;
use App\Contracts\Services\GitHubProvider;
use App\Enums\GitHubOrganizationMembership;
use App\Http\Requests\CreateOrganizationAdminRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\GithubProvider as GitHubOAuthProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Claiming an organization's page asks GitHub, with the read:org scope sign-in doesn't ask for, whether the user
 * belongs to the organization. The token is only used for that, and isn't kept.
 */
final readonly class OrganizationAdminController
{
    private const string CLAIMING = 'claiming-organization';

    /**
     * Claiming starts from a form, so no other site can send a user who already granted the scope through it.
     */
    public function create(CreateOrganizationAdminRequest $request, Organization $organization): SymfonyResponse
    {
        $claimed = $this->claimed($organization);

        if ($claimed !== null) {
            return $this->failed($claimed, to_route('user.show', $organization->handle));
        }

        $request->session()->put(self::CLAIMING, $organization->id);

        return Inertia::location($this->github()->scopes(['read:org'])->redirect()->getTargetUrl());
    }

    /**
     * GitHub only sends back to a path under the callback registered for sign-in, so this is where it lands.
     */
    public function store(Request $request, #[CurrentUser] User $user, GitHubProvider $github, CreateOrganizationAdmin $action): RedirectResponse
    {
        $organization = Organization::query()
            ->find($request->session()->pull(self::CLAIMING));

        if (! $organization instanceof Organization) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Unable to claim this organization. Please try again.')]);

            return to_route('log.show', $user->log);
        }

        $page = to_route('user.show', $organization->handle);

        // Claimed by someone else while the user was on GitHub...
        $claimed = $this->claimed($organization);

        if ($claimed !== null) {
            return $this->failed($claimed, $page);
        }

        if ($request->has('error')) {
            return $this->failed(__('GitHub was not asked about your organizations, so the page was not claimed.'), $page);
        }

        try {
            $githubUser = $this->github()->user();
        } catch (InvalidStateException) {
            return $this->failed(__('Unable to claim this organization. Please try again.'), $page);
        }

        assert($githubUser instanceof GitHubUser);

        if ((string) $githubUser->getId() !== $user->github_id) {
            return $this->failed(__('GitHub is signed in as @:login. Sign in there as @:handle to claim this page.', [
                'login' => $githubUser->getNickname(),
                'handle' => $user->profile->handle,
            ]), $page);
        }

        $handle = $organization->handle;

        // An organization restricting OAuth apps hides its members from those its owners haven't approved, which they
        // do from the app's page in their settings, where members request it.
        $failure = match ($github->organizationMembership($githubUser->token, $handle, $organization->profile->github_id)) {
            GitHubOrganizationMembership::Member => null,
            GitHubOrganizationMembership::NotMember => [__('GitHub says you are not a member of @:handle.', ['handle' => $handle]), null],
            GitHubOrganizationMembership::Restricted => [
                __('@:handle only shares its members with apps its owners approved. On GitHub, grant /dev/null access if you are an owner, or request it from them, then try again.', ['handle' => $handle]),
                'https://github.com/settings/connections/applications/'.config()->string('services.github.client_id'),
            ],
            GitHubOrganizationMembership::SingleSignOn => [
                __('@:handle signs its members in with single sign-on. Sign in to @:handle on GitHub, then try again.', ['handle' => $handle]),
                "https://github.com/orgs/{$handle}/sso",
            ],
        };

        if ($failure !== null) {
            return $this->failed($failure[0], $page, $failure[1]);
        }

        if (! $action->handle($organization, $user)) {
            return $this->failed($this->claimed($organization) ?? __('Unable to claim this organization. Please try again.'), $page);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You are now an admin of @:handle.', ['handle' => $organization->handle])]);

        return $page;
    }

    /**
     * GitHub sends the user back here rather than to sign-in, and checks the token is redeemed for the same URL.
     */
    private function github(): GitHubOAuthProvider
    {
        $provider = Socialite::driver('github');
        assert($provider instanceof GitHubOAuthProvider);

        return $provider->redirectUrl(route('organization-admin.store'));
    }

    /**
     * Only the first claim through GitHub makes an admin. Why another one fails, if the page was claimed.
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
     * The page says why the claim failed until the user leaves it, with where to approve /dev/null when that's why.
     */
    private function failed(string $message, RedirectResponse $redirect, ?string $approvalUrl = null): RedirectResponse
    {
        Inertia::flash('claimFailed', ['message' => $message, 'approvalUrl' => $approvalUrl]);

        return $redirect;
    }
}
