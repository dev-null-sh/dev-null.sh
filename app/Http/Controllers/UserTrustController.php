<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserTrust;
use App\Http\Requests\UpdateUserTrustRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserTrustController
{
    public function update(UpdateUserTrustRequest $request, #[CurrentUser] User $moderator, User $user, UpdateUserTrust $action): RedirectResponse
    {
        $action->handle($user, $moderator, $request->boolean('is_trusted'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->boolean('is_trusted') ? __('Author trusted. Future commits publish immediately.') : __('Trust revoked. Future commits require review.'),
        ]);

        return back();
    }
}
