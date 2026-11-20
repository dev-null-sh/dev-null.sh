<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGuildMute;
use App\Http\Requests\CreateApiGuildMuteRequest;
use App\Http\Requests\DeleteApiGuildMuteRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class GuildMuteController
{
    /**
     * Muting keeps a membership but overrides it in logs, as the web's endpoint does.
     */
    public function store(CreateApiGuildMuteRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMute $action): Response
    {
        $action->handle($user, $guild, true);

        return response()->noContent();
    }

    public function destroy(DeleteApiGuildMuteRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMute $action): Response
    {
        $action->handle($user, $guild, false);

        return response()->noContent();
    }
}
