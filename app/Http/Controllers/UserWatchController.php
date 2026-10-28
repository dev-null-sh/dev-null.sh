<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserWatch;
use App\Http\Requests\CreateUserWatchRequest;
use App\Http\Requests\DeleteUserWatchRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserWatchController
{
    public function store(CreateUserWatchRequest $request, #[CurrentUser] User $currentUser, User $user, UpdateUserWatch $action): RedirectResponse
    {
        $action->handle($currentUser, $user, watching: true);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "You'll be notified when {$user->mention()} commits.",
        ]);

        return back();
    }

    public function destroy(DeleteUserWatchRequest $request, #[CurrentUser] User $currentUser, User $user, UpdateUserWatch $action): RedirectResponse
    {
        $action->handle($currentUser, $user, watching: false);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "You won't be notified when {$user->mention()} commits.",
        ]);

        return back();
    }
}
