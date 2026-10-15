<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUser;
use App\Http\Requests\DeleteUserRequest;
use App\Models\User;
use App\Queries\UserActivityQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    public function show(#[CurrentUser] ?User $currentUser, User $user, UserActivityQuery $activity): RedirectResponse|Response
    {
        if (! $currentUser instanceof User) {
            return $this->preview($user);
        }

        return Inertia::render('user/show', [
            'user' => fn (): User => $user->loadCounts($currentUser),
            'isFollowing' => fn (): bool => $currentUser->isFollowing($user),
            'activity' => fn (): array => $activity->get($user, $currentUser),
            'commits' => Inertia::scroll(fn () => $user->commits()
                ->visibleTo($currentUser)
                ->topLevel()
                ->withVisibleReplyCount($currentUser)
                ->with('user')
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

    /**
     * Guests only get the landing page, with the profile's link preview for crawlers.
     */
    private function preview(User $user): RedirectResponse|Response
    {
        redirect()->setIntendedUrl(route('user.show', $user));

        if (app()->isLocal() && config('app.auto_login')) {
            return to_route('home');
        }

        $user->loadPublicCounts();

        return Inertia::render('welcome')->withViewData(['preview' => [
            'type' => 'profile',
            'title' => "{$user->nameWithHandle()} on /dev/null",
            'description' => $user->bio ?? __("Sign in with GitHub to read @:username's commits on /dev/null.", ['username' => $user->username]),
            'url' => route('user.show', $user),
            'image' => route('user-open-graph-image.show', ['user' => $user, 'v' => $user->openGraphImageVersion()]),
        ]]);
    }
}
