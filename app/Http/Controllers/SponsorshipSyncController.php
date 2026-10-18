<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateSponsorships;
use App\Dto\GitHubSponsors;
use App\Http\Requests\CreateSponsorshipSyncRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\GitHubSponsorsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as GitHubUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final readonly class SponsorshipSyncController
{
    /**
     * Goes through GitHub again for a token that can read the user's sponsors. GitHub doesn't ask again once the app
     * is authorized, and the token is used for the one request and never kept.
     */
    public function create(): SymfonyRedirectResponse
    {
        /** @var GithubProvider $github */
        $github = Socialite::driver('github');

        return $github->redirectUrl(route('sponsorship-sync.store'))->redirect();
    }

    public function store(CreateSponsorshipSyncRequest $request, #[CurrentUser] User $user, GitHubSponsorsQuery $query, UpdateSponsorships $action): RedirectResponse
    {
        $githubUser = $request->githubUser();
        $sponsors = $githubUser instanceof GitHubUser ? $query->get($githubUser->token, $user->username, withSponsors: true) : null;

        if (! $sponsors instanceof GitHubSponsors) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Could not read your sponsors from GitHub. Try again in a minute.']);

            return to_route('user-profile.edit');
        }

        $action->handle($user, $sponsors);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Synced your sponsors from GitHub.']);

        return to_route('org.show', Conversation::sponsorsOrgOf($user));
    }
}
