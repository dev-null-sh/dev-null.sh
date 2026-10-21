<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserTimeout;
use App\Actions\DeleteUserTimeout;
use App\Http\Requests\CreateUserTimeoutRequest;
use App\Http\Requests\DeleteUserTimeoutRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserTimeoutController
{
    public function store(CreateUserTimeoutRequest $request, User $user, CreateUserTimeout $action): RedirectResponse
    {
        $duration = $request->duration();

        $action->handle($user, $request->reason(), $duration);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Put {$user->mention()} in timeout for {$duration->label()}.",
        ]);

        return back();
    }

    public function destroy(DeleteUserTimeoutRequest $request, User $user, DeleteUserTimeout $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Ended {$user->mention()}'s timeout.",
        ]);

        return back();
    }
}
