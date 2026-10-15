<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\CommitBuilder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserPreviewController
{
    public function show(#[CurrentUser] User $currentUser, User $user): JsonResponse
    {
        $user->loadCount([
            'commits' => fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($currentUser),
            'followers',
            'following',
        ]);

        return response()->json([
            'user' => $user,
            'isFollowing' => $currentUser->isFollowing($user),
            'followsYou' => $user->isFollowing($currentUser),
        ]);
    }
}
