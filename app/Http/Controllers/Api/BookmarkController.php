<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateBookmark;
use App\Actions\DeleteBookmark;
use App\Http\Requests\CreateApiBookmarkRequest;
use App\Http\Requests\DeleteApiBookmarkRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use App\Queries\ReplyPreviewsQuery;
use App\Queries\SavedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class BookmarkController
{
    /**
     * The commits the user saved, last saved first, with the newest reply under each, as on the web.
     */
    public function index(#[CurrentUser] User $user, SavedCommitsQuery $saved, ReplyPreviewsQuery $replyPreviews): AnonymousResourceCollection
    {
        $commits = $saved->builder($user)->cursorPaginate(20);

        $replyPreviews->load($commits->items(), $user);

        return CommitResource::collection($commits);
    }

    /**
     * Answers with the commit as its page shows it, so the app gets the new is_saved.
     */
    public function store(CreateApiBookmarkRequest $request, #[CurrentUser] User $user, Commit $commit, CreateBookmark $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }

    public function destroy(DeleteApiBookmarkRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteBookmark $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $commit);

        return CommitResource::make($page->get($commit, $user));
    }
}
