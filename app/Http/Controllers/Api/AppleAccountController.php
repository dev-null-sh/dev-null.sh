<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteUserAppleId;
use App\Actions\UpdateUserAppleId;
use App\Http\Requests\DeleteApiAppleAccountRequest;
use App\Http\Requests\UpdateApiAppleAccountRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class AppleAccountController
{
    /**
     * Links the Apple ID to the user's account, so the app can sign them in with Apple on another device.
     */
    public function update(UpdateApiAppleAccountRequest $request, #[CurrentUser] User $user, UpdateUserAppleId $action): UserResource
    {
        $action->handle($user, $request->appleId());

        return UserResource::make($user->loadCounts())->additional(['apple_linked' => true]);
    }

    public function destroy(DeleteApiAppleAccountRequest $request, #[CurrentUser] User $user, DeleteUserAppleId $action): UserResource
    {
        $action->handle($user);

        return UserResource::make($user->loadCounts())->additional(['apple_linked' => false]);
    }
}
