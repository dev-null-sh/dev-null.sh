<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUser;
use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\ShowUserRequest;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\UserActivityQuery;
use App\Queries\UserStreakQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    public function show(ShowUserRequest $request, #[CurrentUser] ?User $currentUser, User $user, UserActivityQuery $activity, UserStreakQuery $streaks): RedirectResponse|Response
    {
        if (! $currentUser instanceof User) {
            return $this->preview($request, $user);
        }

        return Inertia::render('user/show', [
            'user' => fn (): User => $user->loadCounts($currentUser),
            'isFollowing' => fn (): bool => $currentUser->isFollowing($user),
            'activity' => fn (): array => $activity->get($user, $currentUser),
            'streaks' => fn (): array => $streaks->get($user, $currentUser),
            'tab' => $request->tab(...),
            'commits' => Inertia::scroll(fn () => $user->commits()
                ->visibleTo($currentUser)
                ->when(
                    $request->tab() === 'replies',
                    fn (CommitBuilder $query): CommitBuilder => $query
                        ->whereNotNull('parent_id')
                        ->with(['parent' => fn (BelongsTo $query): BelongsTo => $query
                            ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($currentUser))
                            ->with('user')]),
                    fn (CommitBuilder $query): CommitBuilder => $query->topLevel(),
                )
                ->withVisibleReplyCount($currentUser)
                ->with(['user', 'linkPreview'])
                ->withStarsFor($currentUser)
                ->latest()
                ->latest('id')
                ->cursorPaginate(20)
                ->appends(['tab' => $request->tab()])),
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
    private function preview(ShowUserRequest $request, User $user): RedirectResponse|Response
    {
        redirect()->setIntendedUrl(route('user.show', [
            'user' => $user,
            ...($request->tab() === 'replies' ? ['tab' => 'replies'] : []),
        ]));

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
