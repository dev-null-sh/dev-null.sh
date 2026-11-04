<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateLocalUser;
use App\Http\Requests\CreateLocalSessionRequest;
use App\Services\AppSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Locally, developers sign in as any handle, without GitHub. The routes are only registered in the local
 * environment, see `routes/web.php`.
 */
final readonly class LocalSessionController
{
    /**
     * Sign-in links lead back to the page the guest was reading, where a modal asks for the handle.
     */
    public function create(): RedirectResponse
    {
        Inertia::flash('localSignIn', true);

        return back(fallback: route('home'));
    }

    /**
     * Signing in loads the page in full, as signing in with GitHub does, so no state of the guest's page carries over.
     */
    public function store(CreateLocalSessionRequest $request, CreateLocalUser $action, AppSignIn $appSignIn): SymfonyResponse
    {
        $user = $action->handle($request->string('handle')->value());

        // Signed in from the iOS app POC's sheet: the app's web view signs in, not this browser (temporary, see AppSignIn)...
        $appCallbackUrl = $appSignIn->handOff($request, $user);

        if ($appCallbackUrl !== null) {
            return Inertia::location($appCallbackUrl);
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return Inertia::location(redirect()->intended(route('log.show', $user->log, absolute: false)));
    }
}
