<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteCommitPin;
use App\Actions\UpdateCommitPin;
use App\Http\Requests\DeleteApiCommitPinRequest;
use App\Http\Requests\UpdateApiCommitPinRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class CommitPinController
{
    /**
     * Pins the commit to the top of the user's profile, in place of the one pinned before, and answers with the commit
     * as its page shows it, so the app gets the new is_pinned.
     */
    public function update(UpdateApiCommitPinRequest $request, #[CurrentUser] User $user, Commit $commit, UpdateCommitPin $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }

    public function destroy(DeleteApiCommitPinRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteCommitPin $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }
}
