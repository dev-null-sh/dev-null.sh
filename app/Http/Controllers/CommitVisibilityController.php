<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitVisibilityRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitVisibilityController
{
    public function show(ShowCommitVisibilityRequest $request, #[CurrentUser] User $user, Commit $commit, CommitPageQuery $page): JsonResponse
    {
        $shown = $page->get($commit, $user);
        $shown->isFiltered = false;

        return response()->json($shown)->header('Cache-Control', 'private, no-store');
    }
}
