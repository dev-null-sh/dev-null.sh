<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateGuildRead;
use App\Http\Requests\UpdateGuildReadRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class GuildReadController
{
    public function update(UpdateGuildReadRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildRead $action): RedirectResponse
    {
        $action->handle($user, $guild);

        return back();
    }
}
