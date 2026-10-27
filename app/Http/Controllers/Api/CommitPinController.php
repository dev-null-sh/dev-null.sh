<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteCommitPin;
use App\Actions\UpdateCommitPin;
use App\Http\Requests\DeleteCommitPinRequest;
use App\Http\Requests\UpdateCommitPinRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class CommitPinController
{
    /**
     * Answers with the commit as its page shows it, so the app gets the new is_pinned.
     */
    public function update(UpdateCommitPinRequest $request, #[CurrentUser] User $user, Commit $commit, UpdateCommitPin $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }

    public function destroy(DeleteCommitPinRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteCommitPin $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }
}
