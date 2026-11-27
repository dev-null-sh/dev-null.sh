<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGuildCommitPin;
use App\Exceptions\ApiException;
use App\Exceptions\GuildCommitUnavailable;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Requests\DeleteApiGuildCommitPinRequest;
use App\Http\Requests\UpdateApiGuildCommitPinRequest;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GuildCommitPinController
{
    /**
     * Pins the commit to the guild's board in place of the one pinned before. A moderator removed meanwhile gets the
     * 404 anyone else gets, and a commit the board stopped showing keeps the old pin.
     */
    public function update(UpdateApiGuildCommitPinRequest $request, Guild $guild, Commit $commit, #[CurrentUser] User $user, UpdateGuildCommitPin $action): Response
    {
        try {
            $action->handle($guild, $user, $commit, true);
        } catch (GuildModeratorRemoved) {
            throw new NotFoundHttpException;
        } catch (GuildCommitUnavailable) {
            throw ApiException::commitUnavailable();
        }

        return response()->noContent();
    }

    /**
     * Unpinning a commit that isn't the pin anymore changes nothing.
     */
    public function destroy(DeleteApiGuildCommitPinRequest $request, Guild $guild, Commit $commit, #[CurrentUser] User $user, UpdateGuildCommitPin $action): Response
    {
        try {
            $action->handle($guild, $user, $commit, false);
        } catch (GuildModeratorRemoved) {
            throw new NotFoundHttpException;
        }

        return response()->noContent();
    }
}
