<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateGuildMembership;
use App\Http\Requests\UpdateGuildMembershipRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class GuildMembershipController
{
    public function update(UpdateGuildMembershipRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMembership $action): RedirectResponse
    {
        $action->handle($user, $guild, $request->boolean('joined'));

        return back();
    }
}
