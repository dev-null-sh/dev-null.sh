<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitRepliesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitReplyController
{
    public function index(#[CurrentUser] User $user, Commit $commit, CommitRepliesQuery $replies): JsonResponse
    {
        abort_unless(Commit::query()->withTrashed()->visibleTo($user)->whereKey($commit->id)->exists(), 404);

        return response()->json($replies->paginate($commit, $user));
    }
}
