<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserProfile;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserProfileController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('user-profile/edit', [
            'preferredLane' => $user->preferredLane(),
            'automaticLane' => $user->automaticLane(),
            'githubSyncedAt' => $user->github_synced_at?->toIso8601String(),
            'activityGraphUrl' => route('user-activity-graph.show', $user),
            'profileUrl' => route('user.show', $user),
            'sponsorsOrg' => function () use ($user): ?array {
                $org = Conversation::sponsorsOrgOf($user);

                return $org instanceof Conversation ? [
                    'id' => $org->id,
                    'from' => $org->sponsors_from,
                    'sponsors' => $org->participants()->count() - 1,
                ] : null;
            },
        ]);
    }

    public function update(#[CurrentUser] User $user, UpdateUserProfile $action): RedirectResponse
    {
        $synced = $action->handle($user);

        Inertia::flash('toast', $synced ? [
            'type' => 'success',
            'message' => 'Synced from GitHub.',
        ] : [
            'type' => 'error',
            'message' => 'Could not sync from GitHub. Try again in a minute.',
        ]);

        return back();
    }
}
