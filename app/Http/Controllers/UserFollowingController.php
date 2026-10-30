<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Http\Requests\ListUserFollowingRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowingController
{
    public function index(ListUserFollowingRequest $request, #[CurrentUser] User $currentUser, User $user): Response
    {
        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => ConnectionType::Following,
            'users' => Inertia::scroll(fn () => $user->following()
                ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($currentUser->id)])
                ->latest('follows.created_at')
                ->latest('users.id')
                ->cursorPaginate(30)),
        ]);
    }
}
