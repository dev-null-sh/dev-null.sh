<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserProfileController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('user-profile/edit', [
            'preferredLane' => $user->preferred_lane,
            'automaticLane' => $user->automaticLane(),
        ]);
    }
}
