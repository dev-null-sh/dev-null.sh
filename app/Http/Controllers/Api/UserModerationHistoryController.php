<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\ModerationHistoryResource;
use App\Models\User;
use App\Queries\UserModerationQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserModerationHistoryController
{
    /**
     * The user's own timeouts and bans, which the web shows on their profile. Admins' views of others stay on the web.
     */
    public function show(#[CurrentUser] User $user, UserModerationQuery $moderation): JsonResponse
    {
        return new ModerationHistoryResource($moderation->history($user))
            ->response()
            ->header('Cache-Control', 'private, no-store');
    }
}
