<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserCommitVisibility;
use App\Http\Requests\UpdateApiUserCommitVisibilityRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserCommitVisibilityController
{
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['hidden_commit_channels' => $user->hidden_commit_channels]);
    }

    public function update(UpdateApiUserCommitVisibilityRequest $request, #[CurrentUser] User $user, UpdateUserCommitVisibility $action): JsonResponse
    {
        $action->handle($user, $request->channels());

        return response()->json(['hidden_commit_channels' => $user->hidden_commit_channels]);
    }
}
