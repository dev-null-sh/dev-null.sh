<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\VoteOnPoll;
use App\Http\Requests\CreatePollVoteRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitPollVoteController
{
    public function store(CreatePollVoteRequest $request, #[CurrentUser] User $user, Commit $commit, VoteOnPoll $action): JsonResponse
    {
        $action->handle($user, $request->option());

        return response()->json([
            'poll' => Commit::query()->withPollFor($user)->findOrFail($commit->id)->poll,
        ]);
    }
}
