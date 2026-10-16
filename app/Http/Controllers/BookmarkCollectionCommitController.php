<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBookmarkCollectionCommit;
use App\Actions\DeleteBookmarkCollectionCommit;
use App\Http\Requests\CreateBookmarkCollectionCommitRequest;
use App\Http\Requests\DeleteBookmarkCollectionCommitRequest;
use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class BookmarkCollectionCommitController
{
    public function store(CreateBookmarkCollectionCommitRequest $request, #[CurrentUser] User $user, BookmarkCollection $bookmarkCollection, Commit $commit, CreateBookmarkCollectionCommit $action): JsonResponse
    {
        $action->handle($user, $bookmarkCollection, $commit);

        return response()->json(['in_collection' => true]);
    }

    public function destroy(DeleteBookmarkCollectionCommitRequest $request, #[CurrentUser] User $user, BookmarkCollection $bookmarkCollection, Commit $commit, DeleteBookmarkCollectionCommit $action): JsonResponse
    {
        $action->handle($user, $bookmarkCollection, $commit);

        return response()->json(['in_collection' => false]);
    }
}
