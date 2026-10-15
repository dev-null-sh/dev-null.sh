<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUser;
use App\Http\Requests\DeleteUserRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    public function show(#[CurrentUser] User $currentUser, User $user): Response
    {
        $user->loadCount(['posts', 'followers', 'following']);

        return Inertia::render('user/show', [
            'user' => $user,
            'isFollowing' => $currentUser->isFollowing($user),
            'posts' => Inertia::scroll(fn () => $user->posts()
                ->latest()
                ->latest('id')
                ->cursorPaginate(20)),
        ]);
    }

    public function destroy(DeleteUserRequest $request, #[CurrentUser] User $user, DeleteUser $action): RedirectResponse
    {
        Auth::logout();

        $action->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
