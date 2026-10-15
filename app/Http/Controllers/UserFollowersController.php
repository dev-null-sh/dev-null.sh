<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowersController
{
    public function index(User $user): Response
    {
        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => 'followers',
            'users' => Inertia::scroll(fn () => $user->followers()
                ->latest('follows.created_at')
                ->latest('users.id')
                ->cursorPaginate(30)),
        ]);
    }
}
