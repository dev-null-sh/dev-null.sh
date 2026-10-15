<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RepoPetStatus;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserProfileController
{
    public function edit(#[CurrentUser] User $user, RepoPetStatus $pet): Response
    {
        return Inertia::render('user-profile/edit', [
            'preferredLane' => $user->preferred_lane,
            'automaticLane' => $user->automaticLane(),
            'repoPetConfiguration' => $user->repo_pet,
            'repoPet' => fn (): ?array => $pet->get($user->repo_pet),
        ]);
    }
}
