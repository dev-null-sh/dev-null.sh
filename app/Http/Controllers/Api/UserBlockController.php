<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateUserBlock;
use App\Actions\DeleteUserBlock;
use App\Http\Requests\CreateApiUserBlockRequest;
use App\Http\Requests\DeleteApiUserBlockRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserBlockController
{
    /**
     * The people the user blocks, last blocked first, as Settings → Safety lists them, 20 a page.
     */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        return UserResource::collection($user->blocking()->latest('blocks.created_at')->latest('users.id')->cursorPaginate(20));
    }

    /**
     * Blocks the user, which ends the follows both ways, as on the web, and answers with them and that they are now
     * blocked.
     */
    public function store(CreateApiUserBlockRequest $request, #[CurrentUser] User $currentUser, User $user, CreateUserBlock $action): UserResource
    {
        $action->handle($currentUser, $user);

        return UserResource::make($user)->additional(['is_blocking' => true]);
    }

    /**
     * Unblocking doesn't bring back the follows, as on the web.
     */
    public function destroy(DeleteApiUserBlockRequest $request, #[CurrentUser] User $currentUser, User $user, DeleteUserBlock $action): UserResource
    {
        $action->handle($currentUser, $user);

        return UserResource::make($user)->additional(['is_blocking' => false]);
    }
}
