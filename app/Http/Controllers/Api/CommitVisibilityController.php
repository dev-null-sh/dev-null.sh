<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiCommitVisibilityRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class CommitVisibilityController
{
    public function show(ShowApiCommitVisibilityRequest $request, #[CurrentUser] User $user, Commit $commit, CommitPageQuery $page): CommitResource
    {
        $shown = $page->get($commit, $user);
        $shown->isFiltered = false;

        return CommitResource::make($shown);
    }
}
