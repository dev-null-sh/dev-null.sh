<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowersController
{
    public function index(#[CurrentUser] User $currentUser, User $user): Response
    {
        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => 'followers',
            'users' => Inertia::scroll(fn () => $user->followers()
                ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($currentUser->id)])
                ->latest('follows.created_at')
                ->latest('users.id')
                ->cursorPaginate(30)),
        ]);
    }
}
