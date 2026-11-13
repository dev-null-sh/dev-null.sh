<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserEventTheme;
use App\Http\Requests\UpdateUserEventThemeRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserEventThemeController
{
    /**
     * Saves the event theme on the account, so every browser and app the user signs in to shows the same one.
     */
    public function update(UpdateUserEventThemeRequest $request, #[CurrentUser] User $user, UpdateUserEventTheme $action): RedirectResponse
    {
        $action->handle($user, $request->choice());

        return back();
    }
}
