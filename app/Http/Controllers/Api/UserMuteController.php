<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateUserMute;
use App\Actions\DeleteUserMute;
use App\Http\Requests\CreateApiUserMuteRequest;
use App\Http\Requests\DeleteApiUserMuteRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserMuteController
{
    /**
     * The people the user mutes, last muted first, as Settings → Safety lists them, 20 a page.
     */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        return UserResource::collection($user->muting()->latest('mutes.created_at')->latest('users.id')->cursorPaginate(20));
    }

    /**
     * Mutes the user silently, as on the web, and answers with them and that they are now muted.
     */
    public function store(CreateApiUserMuteRequest $request, #[CurrentUser] User $currentUser, User $user, CreateUserMute $action): UserResource
    {
        $action->handle($currentUser, $user);

        return UserResource::make($user)->additional(['is_muting' => true]);
    }

    public function destroy(DeleteApiUserMuteRequest $request, #[CurrentUser] User $currentUser, User $user, DeleteUserMute $action): UserResource
    {
        $action->handle($currentUser, $user);

        return UserResource::make($user)->additional(['is_muting' => false]);
    }
}
