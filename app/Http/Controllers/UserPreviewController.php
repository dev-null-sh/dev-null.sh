<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserPreviewController
{
    public function show(#[CurrentUser] ?User $currentUser, User $user): JsonResponse
    {
        if (! $currentUser instanceof User) {
            return response()->json([
                'user' => $user->loadPublicCounts(),
                'isFollowing' => false,
                'followsYou' => false,
            ]);
        }

        return response()->json([
            'user' => $user->loadCounts($currentUser),
            'isFollowing' => $currentUser->isFollowing($user),
            'followsYou' => $user->isFollowing($currentUser),
        ]);
    }
}
