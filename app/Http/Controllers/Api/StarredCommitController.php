<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CommitResource;
use App\Models\User;
use App\Queries\ReplyPreviewsQuery;
use App\Queries\StarredCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class StarredCommitController
{
    public function index(#[CurrentUser] User $user, StarredCommitsQuery $starred, ReplyPreviewsQuery $replyPreviews): AnonymousResourceCollection
    {
        $commits = $starred->builder($user)->cursorPaginate(20);
        $replyPreviews->load($commits->items(), $user);

        return CommitResource::collection($commits);
    }
}
