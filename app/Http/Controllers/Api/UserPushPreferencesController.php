<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserPushPreferences;
use App\Enums\PushPreference;
use App\Http\Requests\UpdateApiUserPushPreferencesRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserPushPreferencesController
{
    /**
     * Saves what the user wants pushed to their phones, and answers with all of it, as GET /api/v1/me/settings shows
     * it under `push`.
     */
    public function update(UpdateApiUserPushPreferencesRequest $request, #[CurrentUser] User $user, UpdateUserPushPreferences $action): JsonResponse
    {
        $action->handle($user, $request->preferences());

        return response()->json(['data' => PushPreference::allFor($user)])->header('Cache-Control', 'private, no-store');
    }
}
