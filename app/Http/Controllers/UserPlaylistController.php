<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUserPlaylist;
use App\Actions\UpdateUserPlaylist;
use App\Http\Requests\UpdateUserPlaylistRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserPlaylistController
{
    public function update(UpdateUserPlaylistRequest $request, #[CurrentUser] User $user, UpdateUserPlaylist $action): RedirectResponse
    {
        $action->handle($user, $request->playlistUrl());

        return back();
    }

    public function destroy(#[CurrentUser] User $user, DeleteUserPlaylist $action): RedirectResponse
    {
        $action->handle($user);

        return back();
    }
}
