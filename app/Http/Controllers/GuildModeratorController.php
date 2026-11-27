<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteGuildModerator;
use App\Http\Requests\DeleteGuildModeratorRequest;
use App\Models\Guild;
use Illuminate\Http\RedirectResponse;

final readonly class GuildModeratorController
{
    public function destroy(DeleteGuildModeratorRequest $request, Guild $guild, DeleteGuildModerator $action): RedirectResponse
    {
        $action->handle($guild);

        return to_route('guild.show', $guild->handle);
    }
}
