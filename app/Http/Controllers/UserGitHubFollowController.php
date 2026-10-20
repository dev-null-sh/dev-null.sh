<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserGitHubFollow;
use App\Http\Requests\CreateUserGitHubFollowRequest;
use App\Models\User;
use App\Services\GitHub;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Follows someone on GitHub, one click at a time. GitHub grants the follow scope for that click, and the token it hands
 * over follows the one account and is revoked before the response, so it is never stored. Signing in never asks for it.
 */
final readonly class UserGitHubFollowController
{
    public function create(CreateUserGitHubFollowRequest $request, User $user): Response
    {
        $request->session()->put('github-follow', $user->id);

        return Inertia::location(Socialite::driver('github-follow')->redirect());
    }

    public function store(Request $request, #[CurrentUser] User $follower, CreateUserGitHubFollow $action, GitHub $github): RedirectResponse
    {
        $user = User::query()->find($request->session()->pull('github-follow'));

        if (! $user instanceof User) {
            return to_route('log.show', $follower->log);
        }

        if ($request->has('error')) {
            return $this->toast($user, 'error', 'GitHub was not allowed to follow. Nothing changed.');
        }

        try {
            $githubUser = Socialite::driver('github-follow')->user();
        } catch (InvalidStateException) {
            return $this->toast($user, 'error', 'Unable to follow on GitHub. Please try again.');
        }

        assert($githubUser instanceof GitHubUser);

        try {
            // The browser can be signed in to GitHub as someone else, like a work account...
            if ((string) $githubUser->getId() !== $follower->github_id) {
                return $this->toast($user, 'error', "GitHub is signed in to another account. Switch to {$follower->mention()} on GitHub and try again.");
            }

            $followed = $action->handle($githubUser->token, $user);
        } catch (ConnectionException|RequestException) {
            return $this->toast($user, 'error', "GitHub did not follow {$user->mention()}. Try again in a minute.");
        } finally {
            $github->revoke($githubUser->token);
        }

        return $followed
            ? $this->toast($user, 'success', "You follow {$user->mention()} on GitHub.")
            : $this->toast($user, 'error', "{$user->mention()} is no longer on GitHub.");
    }

    private function toast(User $user, string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('user.show', $user);
    }
}
