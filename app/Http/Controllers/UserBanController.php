<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserBan;
use App\Actions\DeleteBan;
use App\Http\Requests\CreateUserBanRequest;
use App\Http\Requests\DeleteUserBanRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserBanController
{
    public function store(CreateUserBanRequest $request, User $user, CreateUserBan $action): RedirectResponse
    {
        $action->handle($user, $request->reason());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Banned {$user->mention()}. All they can do now is delete their account.",
        ]);

        return back();
    }

    public function destroy(DeleteUserBanRequest $request, User $user, DeleteBan $action): RedirectResponse
    {
        $action->handle($user->github_id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Unbanned {$user->mention()}.",
        ]);

        return back();
    }
}
