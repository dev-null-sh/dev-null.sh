<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUserNowPlaying;
use App\Actions\UpdateUserNowPlaying;
use App\Http\Requests\UpdateUserNowPlayingRequest;
use App\Models\User;
use App\Queries\NowPlayingQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final readonly class UserNowPlayingController
{
    public function show(#[CurrentUser] ?User $currentUser, User $user, NowPlayingQuery $nowPlaying): JsonResponse
    {
        return response()->json([
            'track' => $currentUser?->blockerIdWith($user) === null ? $nowPlaying->get($user) : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateUserNowPlayingRequest $request, #[CurrentUser] User $user, UpdateUserNowPlaying $action): RedirectResponse
    {
        $action->handle($user, $request->lastFmUsername());

        return back();
    }

    public function destroy(#[CurrentUser] User $user, DeleteUserNowPlaying $action): RedirectResponse
    {
        $action->handle($user);

        return back();
    }
}
