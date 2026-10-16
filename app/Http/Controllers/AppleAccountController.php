<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUserAppleId;
use App\Http\Requests\DeleteAppleAccountRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class AppleAccountController
{
    /**
     * Unlinks Sign in with Apple from the web, so it can be revoked without the app.
     */
    public function destroy(DeleteAppleAccountRequest $request, #[CurrentUser] User $user, DeleteUserAppleId $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Unlinked Sign in with Apple.'),
        ]);

        return back();
    }
}
