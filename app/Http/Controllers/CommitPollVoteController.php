<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitPollVote;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitPollVoteRequest;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitPollVoteController
{
    /**
     * Counts for an open poll the viewer can already see. Nothing is written, and has_voted stays false until they vote.
     */
    public function show(#[CurrentUser] User $user, Commit $commit): JsonResponse
    {
        abort_unless($commit->isVisibleTo($user), 403);

        $poll = Commit::query()->withPollFor($user)->findOrFail($commit->id)->poll;

        if (! $poll instanceof Poll) {
            abort(404);
        }

        if ($commit->status !== CommitStatus::Approved || $poll->opened_at === null) {
            abort(403);
        }

        return response()->json([
            'poll' => $poll->revealingResults(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateCommitPollVoteRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitPollVote $action): JsonResponse
    {
        $action->handle($user, $request->option());

        return response()->json([
            'poll' => Commit::query()->withPollFor($user)->findOrFail($commit->id)->poll,
        ]);
    }
}
