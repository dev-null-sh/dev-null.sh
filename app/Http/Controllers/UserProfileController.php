<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserFeedToken;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserProfileController
{
    public function edit(#[CurrentUser] User $user, UpdateUserFeedToken $updateUserFeedToken): Response
    {
        return Inertia::render('user-profile/edit', [
            'preferredLane' => $user->preferred_lane,
            'automaticLane' => $user->automaticLane(),
            'activityGraphUrl' => route('user-activity-graph.show', $user),
            'profileUrl' => route('user.show', $user),
            'feedUrl' => route('following-feed.show', $user->feed_token ?? $updateUserFeedToken->handle($user)),
        ]);
    }
}
