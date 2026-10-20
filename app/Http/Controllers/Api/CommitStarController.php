<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommitStar;
use App\Actions\DeleteCommitStar;
use App\Http\Requests\CreateApiCommitStarRequest;
use App\Http\Requests\DeleteApiCommitStarRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class CommitStarController
{
    /**
     * Answers with the commit as its page shows it, so the app gets the new stars_count and starred.
     */
    public function store(CreateApiCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitStar $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }

    public function destroy(DeleteApiCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteCommitStar $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }
}
