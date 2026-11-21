<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitPollRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitPollController
{
    /**
     * The poll as the viewer sees it, read again once someone else voted or it closed: its results stay hidden from
     * whoever hasn't voted while it is open.
     */
    public function show(ShowCommitPollRequest $request, #[CurrentUser] ?User $user, Commit $commit): JsonResponse
    {
        return response()->json([
            'poll' => Commit::query()->withPollFor($user)->findOrFail($commit->id)->poll,
        ])->header('Cache-Control', 'no-store, private');
    }
}
