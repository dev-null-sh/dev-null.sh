<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RegenerateFeedToken;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class FeedTokenController
{
    public function update(#[CurrentUser] User $user, RegenerateFeedToken $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('RSS link reset. Update it in your feed reader.'),
        ]);

        return back();
    }
}
