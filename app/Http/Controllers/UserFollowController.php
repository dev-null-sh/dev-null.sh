<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserFollow;
use App\Actions\DeleteUserFollow;
use App\Http\Requests\CreateUserFollowRequest;
use App\Http\Requests\DeleteUserFollowRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserFollowController
{
    public function store(CreateUserFollowRequest $request, #[CurrentUser] User $follower, User $user, CreateUserFollow $action): RedirectResponse
    {
        $action->handle($follower, $user);

        return back();
    }

    public function destroy(DeleteUserFollowRequest $request, #[CurrentUser] User $follower, User $user, DeleteUserFollow $action): RedirectResponse
    {
        $action->handle($follower, $user);

        return back();
    }
}
