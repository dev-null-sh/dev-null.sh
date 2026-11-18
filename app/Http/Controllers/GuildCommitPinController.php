<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateGuildCommitPin;
use App\Exceptions\GuildCommitUnavailable;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Requests\UpdateGuildCommitPinRequest;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class GuildCommitPinController
{
    public function update(UpdateGuildCommitPinRequest $request, Guild $guild, Commit $commit, #[CurrentUser] User $user, UpdateGuildCommitPin $action): RedirectResponse
    {
        $response = to_route('guild.show', $guild->handle);

        try {
            $action->handle($guild, $user, $commit, $request->boolean('pinned'));
        } catch (GuildModeratorRemoved) {
            return $response->withErrors(['pin' => 'You no longer moderate this guild.']);
        } catch (GuildCommitUnavailable) {
            return $response->withErrors(['pin' => 'This commit is no longer available on the board.']);
        }

        return $response;
    }
}
