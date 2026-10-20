<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserTranslationLanguage;
use App\Http\Requests\UpdateUserTranslationLanguageRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserTranslationLanguageController
{
    public function update(UpdateUserTranslationLanguageRequest $request, #[CurrentUser] User $user, UpdateUserTranslationLanguage $action): RedirectResponse
    {
        $action->handle($user, $request->language());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Translation language saved.',
        ]);

        return back();
    }
}
