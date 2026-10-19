<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSigningRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SigningController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('signing/edit', [
            'passkeys' => $user->passkeys()->latest()->get(),
            'requiresSignedCommits' => $user->requires_signed_commits,
        ]);
    }

    public function update(UpdateSigningRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->update(['requires_signed_commits' => $request->boolean('enabled')]);

        return back();
    }
}
