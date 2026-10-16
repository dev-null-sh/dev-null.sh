<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserPasteisMode;
use App\Http\Requests\UpdateUserPasteisModeRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserPasteisModeController
{
    public function update(UpdateUserPasteisModeRequest $request, #[CurrentUser] User $user, UpdateUserPasteisMode $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('enabled'));

        return back();
    }
}
