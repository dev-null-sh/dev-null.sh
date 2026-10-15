<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserPreviewController
{
    public function show(#[CurrentUser] User $currentUser, User $user): JsonResponse
    {
        $blockerId = $currentUser->blockerIdWith($user);

        return response()->json([
            'user' => $blockerId === null ? $user->loadCounts($currentUser) : $user,
            'isFollowing' => $currentUser->isFollowing($user),
            'followsYou' => $user->isFollowing($currentUser),
            'isMuting' => $currentUser->isMuting($user),
            'isBlocking' => $blockerId === $currentUser->id,
            'isUnavailable' => $blockerId !== null,
        ]);
    }
}
