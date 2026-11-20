<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGuildMembership;
use App\Http\Requests\CreateApiGuildMembershipRequest;
use App\Http\Requests\DeleteApiGuildMembershipRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class GuildMembershipController
{
    /**
     * Joining clears a mute of the guild, as on the web.
     */
    public function store(CreateApiGuildMembershipRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMembership $action): Response
    {
        $action->handle($user, $guild, true);

        return response()->noContent();
    }

    public function destroy(DeleteApiGuildMembershipRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMembership $action): Response
    {
        $action->handle($user, $guild, false);

        return response()->noContent();
    }
}
