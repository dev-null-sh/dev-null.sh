<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserStreakShields;
use App\Http\Requests\UpdateUserStreakShieldsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserStreakShieldsController
{
    public function update(UpdateUserStreakShieldsRequest $request, #[CurrentUser] User $user, UpdateUserStreakShields $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('enabled'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Automatic streak protection saved.']);

        return back();
    }
}
