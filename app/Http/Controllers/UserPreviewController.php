<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Presence;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserPreviewController
{
    public function __construct(private Presence $presence)
    {
        //
    }

    public function show(#[CurrentUser] ?User $currentUser, User $user): JsonResponse
    {
        $online = $this->presence->isOnline($user->last_activity_at);
        $lastActivityAt = $user->last_activity_at?->toIso8601ZuluString();

        if (! $currentUser instanceof User) {
            return response()->json([
                'user' => $user->loadPublicCounts(),
                'online' => $online,
                'lastActivityAt' => $lastActivityAt,
                'isFollowing' => false,
                'followsYou' => false,
                'isMuting' => false,
                'isBlocking' => false,
                'isUnavailable' => false,
            ]);
        }

        $blockerId = $currentUser->blockerIdWith($user);

        return response()->json([
            'user' => $blockerId === null ? $user->loadCounts($currentUser) : $user,
            'online' => $online,
            'lastActivityAt' => $lastActivityAt,
            'isFollowing' => $currentUser->isFollowing($user),
            'followsYou' => $user->isFollowing($currentUser),
            'isMuting' => $currentUser->isMuting($user),
            'isBlocking' => $blockerId === $currentUser->id,
            'isUnavailable' => $blockerId !== null,
        ]);
    }
}
