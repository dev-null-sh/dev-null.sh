<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateGuildMute;
use App\Http\Requests\UpdateGuildMuteRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class GuildMuteController
{
    public function update(UpdateGuildMuteRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMute $action): RedirectResponse
    {
        $action->handle($user, $guild, $request->boolean('muted'));

        return back();
    }
}
