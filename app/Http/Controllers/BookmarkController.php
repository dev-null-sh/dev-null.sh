<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveCommit;
use App\Actions\UnsaveCommit;
use App\Http\Requests\CreateBookmarkRequest;
use App\Http\Requests\DeleteBookmarkRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\SavedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BookmarkController
{
    public function index(#[CurrentUser] User $user, SavedCommitsQuery $saved): Response
    {
        return Inertia::render('bookmark/index', [
            'commits' => Inertia::scroll(fn () => $saved->builder($user)->cursorPaginate(20)),
        ]);
    }

    public function store(CreateBookmarkRequest $request, #[CurrentUser] User $user, Commit $commit, SaveCommit $action): JsonResponse
    {
        $action->handle($user, $commit);

        return response()->json(['is_saved' => true]);
    }

    public function destroy(DeleteBookmarkRequest $request, #[CurrentUser] User $user, Commit $commit, UnsaveCommit $action): JsonResponse
    {
        $action->handle($user, $commit);

        return response()->json(['is_saved' => false]);
    }
}
