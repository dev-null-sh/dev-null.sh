<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CommitResource;
use App\Models\User;
use App\Queries\ReplyPreviewsQuery;
use App\Queries\SavedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class SavedCommitController
{
    public function __invoke(#[CurrentUser] User $user, SavedCommitsQuery $saved, ReplyPreviewsQuery $replyPreviews): AnonymousResourceCollection
    {
        $commits = $saved->builder($user)->cursorPaginate(20);
        $replyPreviews->load($commits->items(), $user);

        return CommitResource::collection($commits);
    }
}
