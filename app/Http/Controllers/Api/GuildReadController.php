<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGuildRead;
use App\Http\Requests\UpdateApiGuildReadRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class GuildReadController
{
    /**
     * Opening a board clears its missed commits, as the web's board does. A guild the user did not join has nothing
     * to clear, so it writes nothing.
     */
    public function update(UpdateApiGuildReadRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildRead $action): Response
    {
        $action->handle($user, $guild);

        return response()->noContent();
    }
}
