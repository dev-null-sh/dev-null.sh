<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiCommitVisibilityRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitVisibilityController
{
    public function show(ShowApiCommitVisibilityRequest $request, #[CurrentUser] User $user, Commit $commit, CommitPageQuery $page): JsonResponse
    {
        $shown = $page->get($commit, $user);
        $shown->isFiltered = false;

        return CommitResource::make($shown)->response($request)->header('Cache-Control', 'private, no-store');
    }
}
