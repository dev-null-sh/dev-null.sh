<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserEmailsUnsubscribedAt;
use App\Http\Requests\CreateUnsubscriptionRequest;
use App\Http\Requests\ShowUnsubscriptionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UnsubscriptionController
{
    /**
     * Opening the link only asks, since mail scanners open every link in an email before the person does.
     */
    public function show(ShowUnsubscriptionRequest $request, User $user): Response
    {
        return Inertia::render('unsubscription/show', [
            'url' => $request->fullUrl(),
            'unsubscribed' => $user->emails_unsubscribed_at !== null,
        ]);
    }

    /**
     * Also where mail providers post their one-click unsubscribe button to.
     */
    public function store(CreateUnsubscriptionRequest $request, User $user, UpdateUserEmailsUnsubscribedAt $action): RedirectResponse
    {
        $action->handle($user);

        return redirect($request->fullUrl());
    }
}
