<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserMute;
use App\Actions\DeleteUserMute;
use App\Http\Requests\CreateUserMuteRequest;
use App\Http\Requests\DeleteUserMuteRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserMuteController
{
    public function store(CreateUserMuteRequest $request, #[CurrentUser] User $currentUser, User $user, CreateUserMute $action): RedirectResponse
    {
        $action->handle($currentUser, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Muted {$user->mention()}. You won't see their commits or hear from them, and they won't know.",
        ]);

        return back();
    }

    public function destroy(DeleteUserMuteRequest $request, #[CurrentUser] User $currentUser, User $user, DeleteUserMute $action): RedirectResponse
    {
        $action->handle($currentUser, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Unmuted {$user->mention()}.",
        ]);

        return back();
    }
}
