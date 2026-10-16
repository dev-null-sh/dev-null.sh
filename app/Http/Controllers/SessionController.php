<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrCreateUser;
use App\Jobs\ImportGitHubFollowsJob;
use App\Jobs\SyncGitHubSshKeysJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final readonly class SessionController
{
    public function create(): SymfonyRedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function store(Request $request, UpdateOrCreateUser $action): RedirectResponse
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

        $user = $action->handle($githubUser);

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

        return to_route('home');
    }

    private function failed(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Unable to sign in with GitHub. Please try again.'),
        ]);

        return to_route('home');
    }
}
