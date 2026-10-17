<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBookmark;
use App\Actions\DeleteBookmark;
use App\Http\Requests\CreateBookmarkRequest;
use App\Http\Requests\DeleteBookmarkRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\BookmarkCollectionsQuery;
use App\Queries\SavedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BookmarkController
{
    public function index(#[CurrentUser] User $user, SavedCommitsQuery $saved, BookmarkCollectionsQuery $collections): Response
    {
        return Inertia::render('bookmark/index', [
            'collection' => null,
            'collections' => fn (): Collection => $collections->get($user),
            'commits' => Inertia::scroll(fn () => $saved->builder($user)->cursorPaginate(20)),
        ]);
    }

    public function store(CreateBookmarkRequest $request, #[CurrentUser] User $user, Commit $commit, CreateBookmark $action): JsonResponse
    {
        $action->handle($user, $commit);

        return response()->json(['is_saved' => true]);
    }

    public function destroy(DeleteBookmarkRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteBookmark $action): JsonResponse
    {
        $action->handle($user, $commit);

        return response()->json(['is_saved' => false]);
    }
}
