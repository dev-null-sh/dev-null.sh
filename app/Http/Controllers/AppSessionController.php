<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AppSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Signs in the iOS app POC, see {@see AppSignIn}. Temporary, to be reverted.
 */
final readonly class AppSessionController
{
    /**
     * Opened by the app in the system's authentication sheet. A browser already signed in to /dev/null hands the user
     * back to the app at once; otherwise the usual sign-in runs, and hands back instead of signing in this browser.
     */
    public function create(Request $request, AppSignIn $appSignIn): SymfonyRedirectResponse
    {
        $request->validate([
            'challenge' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{43}\z/'],
        ]);

        $challenge = $request->string('challenge')->value();
        $user = $request->user();

        if ($user instanceof User) {
            return redirect()->away($appSignIn->callbackUrl($user, $challenge));
        }

        $appSignIn->start($request, $challenge);

        // Locally, signing in goes back to the previous page for its modal, which would be this one again...
        if (app()->isLocal()) {
            Inertia::flash('localSignIn', true);

            return to_route('home');
        }

        return to_route('login');
    }

    /**
     * Opened by the app in its web view, with the code the sheet handed back and the verifier only the app knows.
     */
    public function store(Request $request, AppSignIn $appSignIn): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:128'],
            'verifier' => ['required', 'string', 'max:128'],
        ]);

        $redeemed = $appSignIn->redeem($request->string('code')->value(), $request->string('verifier')->value());

        if ($redeemed === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to sign in with GitHub. Please try again.'),
            ]);

            return to_route('home');
        }

        [$user, $signedUp] = $redeemed;

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        if ($signedUp) {
            // Counted by Fathom in the browser, for the conversion rate of the metrics...
            Inertia::flash('signedUp', true);
        }

        if (! $user->isOnboarded()) {
            return to_route('onboarding.show');
        }

        return redirect()->intended(route('log.show', $user->log, absolute: false));
    }
}
