<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserLane;
use App\Http\Requests\UpdateUserLaneRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserLaneController
{
    public function update(UpdateUserLaneRequest $request, #[CurrentUser] User $user, UpdateUserLane $action): RedirectResponse
    {
        $action->handle($user, $request->lane());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Color saved.'),
        ]);

        return back();
    }
}
