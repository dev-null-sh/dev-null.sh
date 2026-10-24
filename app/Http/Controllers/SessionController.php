<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTomFriendship;
use App\Actions\UpdateOrCreateUser;
use App\Jobs\ImportGitHubFollowsJob;
use App\Jobs\SyncGitHubSshKeysJob;
use App\Services\GitHubAccountScreening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final readonly class SessionController
{
    private const string SignedOut = 'signed_out';

    /**
     * GitHub signs an account it has already authorized straight back in, so right after signing out it is asked to let
     * the person pick which account to continue with instead.
     */
    public function create(Request $request): SymfonyRedirectResponse
    {
        $github = Socialite::driver('github');
        assert($github instanceof GithubProvider);

        // with() replaces the parameters rather than adding to them, so a prompt never carries over to a later sign-in.
        return $github->with($request->session()->pull(self::SignedOut) === true ? ['prompt' => 'select_account'] : [])->redirect();
    }

    public function store(Request $request, UpdateOrCreateUser $action, CreateTomFriendship $createTomFriendship, GitHubAccountScreening $screening): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->failed();
        }

        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            return $this->failed();
        }

        assert($githubUser instanceof GitHubUser);

        $refusal = $screening->refusal($githubUser);

        if ($refusal !== null) {
            return $this->failed($refusal);
        }

        $user = $action->handle($githubUser);

        if ($user->wasRecentlyCreated) {
            $createTomFriendship->handle($user);

            // Counted by Fathom in the browser, for the conversion rate of the metrics...
            Inertia::flash('signedUp', true);
        }

        dispatch(new SyncGitHubSshKeysJob($user));
        dispatch(new ImportGitHubFollowsJob($user, notify: $user->wasRecentlyCreated));

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        if (! $user->isOnboarded()) {
            return to_route('onboarding.show');
        }

        return redirect()->intended(route('log.show', $user->log, absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(self::SignedOut, true);

        return to_route('home');
    }

    private function failed(?string $message = null): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message ?? __('Unable to sign in with GitHub. Please try again.'),
        ]);

        return to_route('home');
    }
}
