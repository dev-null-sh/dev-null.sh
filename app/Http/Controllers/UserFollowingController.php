<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowingController
{
    public function index(#[CurrentUser] User $currentUser, User $user): RedirectResponse|Response
    {
        if ($currentUser->hasBlockWith($user)) {
            return to_route('user.show', $user);
        }

        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => 'following',
            'users' => Inertia::scroll(fn () => $user->following()
                ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($currentUser->id)])
                ->latest('follows.created_at')
                ->latest('users.id')
                ->cursorPaginate(30)),
        ]);
    }
}
