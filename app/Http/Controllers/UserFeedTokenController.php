<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserFeedToken;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserFeedTokenController
{
    public function update(#[CurrentUser] User $user, UpdateUserFeedToken $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('RSS link reset. Update it in your feed reader.'),
        ]);

        return back();
    }
}
