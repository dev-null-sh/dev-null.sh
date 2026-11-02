<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserAutomaticTranslation;
use App\Http\Requests\UpdateUserAutomaticTranslationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserAutomaticTranslationController
{
    public function update(UpdateUserAutomaticTranslationRequest $request, #[CurrentUser] User $user, UpdateUserAutomaticTranslation $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('enabled'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Automatic translation preference saved.',
        ]);

        return back();
    }
}
