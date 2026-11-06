<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommitStar;
use App\Actions\DeleteCommitStar;
use App\Http\Requests\CreateApiCommitStarRequest;
use App\Http\Requests\DeleteApiCommitStarRequest;
use App\Http\Requests\ListApiCommitStarsRequest;
use App\Http\Resources\CommitResource;
use App\Http\Resources\UserResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class CommitStarController
{
    /**
     * Who starred the commit, newest first, 20 a page as on the web. People across a block are left out.
     */
    public function index(ListApiCommitStarsRequest $request, #[CurrentUser] User $user, Commit $commit): AnonymousResourceCollection
    {
        return UserResource::collection($commit->stars()
            ->whereNotIn('users.id', $user->idsAcrossBlocks())
            ->latest('stars.created_at')
            ->latest('users.id')
            ->cursorPaginate(20));
    }

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
