<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGuildAlias;
use App\Actions\DeleteGuildAlias;
use App\Http\Requests\CreateGuildAliasRequest;
use App\Http\Requests\DeleteGuildAliasRequest;
use App\Models\Guild;
use App\Models\GuildAlias;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class GuildAliasController
{
    public function store(CreateGuildAliasRequest $request, Guild $guild, #[CurrentUser] User $user, CreateGuildAlias $action): RedirectResponse
    {
        $action->handle($guild, $user, $request->string('handle')->value());

        return to_route('guild.show', $guild->handle);
    }

    public function destroy(DeleteGuildAliasRequest $request, Guild $guild, GuildAlias $alias, DeleteGuildAlias $action): RedirectResponse
    {
        $action->handle($guild, $alias);

        return to_route('guild.show', $guild->handle);
    }
}
