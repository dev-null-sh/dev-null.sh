<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateProfileLookup;
use App\Http\Requests\CreateProfileLookupRequest;
use App\Http\Requests\ShowProfileLookupRequest;
use App\Models\User;
use App\Queries\ProfileLookupQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A handle nobody on /dev/null has may still be someone's on GitHub. Members look it up from its page, which waits for
 * GitHub and then shows the profile it found. Guests and crawlers can't, so they can't spend GitHub's budget: they're
 * asked to sign in, on a missing page.
 */
final readonly class ProfileLookupController
{
    public function show(ShowProfileLookupRequest $request, #[CurrentUser] ?User $currentUser, ProfileLookupQuery $lookups): Response
    {
        // A missing page isn't remembered for after sign-in (RememberIntendedUrl), but this one is where the guest
        // comes back to, to look the handle up...
        if (! $currentUser instanceof User && ! $request->prefetch()) {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return Inertia::render('profile/lookup', [
            'handle' => $request->handle(),
            'lookup' => fn (): ?array => $currentUser instanceof User ? $lookups->get($request->handle()) : null,
        ])->withViewData(['robots' => 'noindex'])->toResponse($request)->setStatusCode($currentUser instanceof User ? 200 : 404);
    }

    public function store(CreateProfileLookupRequest $request, CreateProfileLookup $action): RedirectResponse
    {
        $action->handle($request->handle());

        return back();
    }
}
