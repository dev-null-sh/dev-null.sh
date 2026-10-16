<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitRepliesRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitRepliesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitReplyController
{
    public function index(ListCommitRepliesRequest $request, #[CurrentUser] ?User $user, Commit $commit, CommitRepliesQuery $replies): JsonResponse
    {
        return response()->json($replies->paginate($commit, $user));
    }
}
