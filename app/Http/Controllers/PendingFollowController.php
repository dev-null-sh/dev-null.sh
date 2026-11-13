<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePendingFollow;
use App\Actions\DeletePendingFollow;
use App\Http\Requests\CreatePendingFollowRequest;
use App\Http\Requests\DeletePendingFollowRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Following a developer who isn't on /dev/null yet waits for them to sign up.
 */
final readonly class PendingFollowController
{
    public function store(CreatePendingFollowRequest $request, #[CurrentUser] User $user, Profile $profile, CreatePendingFollow $action): RedirectResponse
    {
        $action->handle($user, $profile);

        return back();
    }

    public function destroy(DeletePendingFollowRequest $request, #[CurrentUser] User $user, Profile $profile, DeletePendingFollow $action): RedirectResponse
    {
        $action->handle($user, $profile);

        return back();
    }
}
