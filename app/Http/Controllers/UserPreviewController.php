<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PushAccess;
use App\Models\User;
use App\Queries\BioProfilesQuery;
use App\Queries\PushAccessQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserPreviewController
{
    public function show(#[CurrentUser] ?User $currentUser, User $user, BioProfilesQuery $bioProfiles, PushAccessQuery $pushAccess): JsonResponse
    {
        if (! $currentUser instanceof User) {
            return response()->json([
                'user' => $user->loadPublicCounts(),
                'bioProfiles' => $bioProfiles->get($user),
                'isFollowing' => false,
                'followsYou' => false,
                'isMuting' => false,
                'isBlocking' => false,
                'isUnavailable' => false,
                'canPush' => false,
            ]);
        }

        $blockerId = $currentUser->blockerIdWith($user);

        return response()->json([
            'user' => $blockerId === null ? $user->loadCounts($currentUser) : $user,
            'bioProfiles' => $bioProfiles->get($user),
            'isFollowing' => $currentUser->isFollowing($user),
            'followsYou' => $user->isFollowing($currentUser),
            'isMuting' => $currentUser->isMuting($user),
            'isBlocking' => $blockerId === $currentUser->id,
            'isUnavailable' => $blockerId !== null,
            'canPush' => $currentUser->canMessage($user)
                && ! in_array($pushAccess->get($currentUser, $user), [PushAccess::Restricted, PushAccess::Closed], true),
        ]);
    }
}
