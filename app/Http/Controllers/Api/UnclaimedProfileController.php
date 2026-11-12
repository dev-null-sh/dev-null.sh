<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiUnclaimedProfileRequest;
use App\Models\Profile;
use App\Models\User;
use App\Queries\PendingFollowsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UnclaimedProfileController
{
    /**
     * A developer looked up from GitHub who isn't on /dev/null yet, as their page on the web shows them, with how many
     * members wait to follow them. A signed request also gets whether its user waits, and who they follow that does.
     */
    public function show(ShowApiUnclaimedProfileRequest $request, #[CurrentUser] ?User $user, PendingFollowsQuery $pendingFollows): JsonResponse
    {
        $profile = $request->profile();
        assert($profile instanceof Profile);

        return response()->json([
            'data' => $profile->only(['id', 'handle', 'name', 'avatar_url', 'bio']),
            'waiting_count' => $pendingFollows->count($profile),
            'is_waiting' => $user instanceof User && $pendingFollows->isWaiting($profile, $user),
            'waiting_you_follow' => $user instanceof User ? $pendingFollows->youFollow($profile, $user) : [],
        ]);
    }
}
