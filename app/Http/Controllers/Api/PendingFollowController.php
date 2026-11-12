<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreatePendingFollow;
use App\Actions\DeletePendingFollow;
use App\Http\Requests\CreateApiPendingFollowRequest;
use App\Http\Requests\DeleteApiPendingFollowRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

/**
 * Following a developer who isn't on /dev/null yet waits for them to sign up.
 */
final readonly class PendingFollowController
{
    public function store(CreateApiPendingFollowRequest $request, #[CurrentUser] User $user, Profile $profile, CreatePendingFollow $action): Response
    {
        $action->handle($user, $profile);

        return response()->noContent();
    }

    public function destroy(DeleteApiPendingFollowRequest $request, #[CurrentUser] User $user, Profile $profile, DeletePendingFollow $action): Response
    {
        $action->handle($user, $profile);

        return response()->noContent();
    }
}
