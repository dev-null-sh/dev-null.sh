<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteGuildLive;
use App\Actions\UpdateGuildLive;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Requests\DeleteGuildLiveRequest;
use App\Http\Requests\UpdateGuildLiveRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class GuildLiveController
{
    public function update(UpdateGuildLiveRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildLive $action): RedirectResponse
    {
        try {
            $action->handle($guild, $user, $request->platform(), $request->url(), $request->integer('hours'));
        } catch (GuildModeratorRemoved) {
            return back()->withErrors(['url' => 'You no longer moderate this guild.']);
        }

        return back();
    }

    public function destroy(DeleteGuildLiveRequest $request, Guild $guild, #[CurrentUser] User $user, DeleteGuildLive $action): RedirectResponse
    {
        try {
            $action->handle($guild, $user);
        } catch (GuildModeratorRemoved) {
            return back()->withErrors(['url' => 'You no longer moderate this guild.']);
        }

        return back();
    }
};
