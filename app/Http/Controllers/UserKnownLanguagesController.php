<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserKnownLanguages;
use App\Http\Requests\UpdateUserKnownLanguagesRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserKnownLanguagesController
{
    public function update(UpdateUserKnownLanguagesRequest $request, #[CurrentUser] User $user, UpdateUserKnownLanguages $action): RedirectResponse
    {
        $action->handle($user, $request->languages());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Languages saved.',
        ]);

        return back();
    }
}
