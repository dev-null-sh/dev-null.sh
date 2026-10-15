<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitReplyController
{
    public function index(#[CurrentUser] User $user, Commit $commit): JsonResponse
    {
        abort_unless(Commit::query()->visibleTo($user)->whereKey($commit->id)->exists(), 404);

        return response()->json([
            'replies' => $commit->replies()
                ->visibleTo($user)
                ->withVisibleReplyCount($user)
                ->with('user')
                ->oldest()
                ->oldest('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
