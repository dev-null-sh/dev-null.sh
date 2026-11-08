<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTomFriendship;
use App\Actions\DeleteDevice;
use App\Actions\DeletePushSubscription;
use App\Actions\UpdateOrCreateUser;
use App\Dto\MobileAuthorization;
use App\Jobs\ImportGitHubFollowsJob;
use App\Jobs\ImportGitHubStarsJob;
use App\Jobs\LookUpUserOrganizations;
use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\Device;
use App\Models\User;
use App\Services\GitHubAccountScreening;
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
    private const string RETRIED = 'github-sign-in-retried';

    public function create(): SymfonyRedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function store(Request $request, UpdateOrCreateUser $action, CreateTomFriendship $createTomFriendship, GitHubAccountScreening $screening): RedirectResponse
    {
        $hasRetried = (bool) $request->session()->pull(self::RETRIED, false);

        if ($request->has('error')) {
            return $this->failed();
        }

        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            if ($hasRetried) {
                return $this->failed();
            }

            // The sign-in started in another browser, typically an app's browser on a phone whose GitHub page was
            // opened in Safari. GitHub is signed in here by now, so starting over comes straight back, with a state.
            $request->session()->put(self::RETRIED, true);

            return to_route('login');
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

        // Ahead of the follows, since onboarding reloads its suggestions once those are in.
        if ($user->github_stars_imported_at === null) {
            dispatch(new ImportGitHubStarsJob($user));
        }

        dispatch(new ImportGitHubFollowsJob($user, notify: $user->wasRecentlyCreated));
        LookUpUserOrganizations::dispatchFor($user);

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        if (! $user->isOnboarded() && ! $this->isApprovingLiteMobileApp($request)) {
            return to_route('onboarding.show');
        }

        return redirect()->intended(route('log.show', $user->log, absolute: false));
    }

    public function destroy(Request $request, DeletePushSubscription $deletePushSubscription, DeleteDevice $deleteDevice): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $endpointHash = $request->session()->get('push_endpoint_hash');
        $deletePushSubscription->handle($user, is_string($endpointHash) ? $endpointHash : '');

        // Signing out of the lite mobile app's web view signs the app out, or it would make a new session right away...
        $device = $user->devices()->whereKey($request->session()->get(Device::SessionKey))->first();

        if ($device instanceof Device) {
            $deleteDevice->handle($device);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }

    /**
     * The lite mobile app onboards a new account in its web view once it is signed in, rather than in its sign-in sheet.
     * The other apps have nowhere else to send them, so they keep onboarding in the browser before approving.
     */
    private function isApprovingLiteMobileApp(Request $request): bool
    {
        $intended = $request->session()->get('url.intended');

        return is_string($intended)
            && str_starts_with($intended, route('mobile-authorization.show').'?')
            && Request::create($intended)->query('app_id') === MobileAuthorization::LiteMobileAppId;
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
