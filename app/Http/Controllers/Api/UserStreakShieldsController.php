<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserStreakShields;
use App\Http\Requests\UpdateApiUserStreakShieldsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserStreakShieldsController
{
    public function update(UpdateApiUserStreakShieldsRequest $request, #[CurrentUser] User $user, UpdateUserStreakShields $action): JsonResponse
    {
        $action->handle($user, $request->boolean('enabled'));

        return response()->json(['data' => ['automatic_streak_shields' => $user->automatic_streak_shields]])->header('Cache-Control', 'private, no-store');
    }
}
