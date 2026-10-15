<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommit;
use App\Actions\DeleteCommit;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitReplyRequest;
use App\Http\Requests\DeleteCommitReplyRequest;
use App\Http\Requests\ListCommitRepliesRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitRepliesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class CommitReplyController
{
    public function index(ListCommitRepliesRequest $request, #[CurrentUser] User $user, Commit $commit, CommitRepliesQuery $replies): JsonResponse
    {
        return response()->json($replies->paginate($commit, $user));
    }

    public function store(CreateCommitReplyRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommit $action): JsonResponse
    {
        $reply = $action->handle($user, $request->string('body')->value(), $commit, $request->images());

        return response()->json([
            'committed' => [
                'id' => $reply->id,
                'body' => $reply->body,
            ],
            ...$reply->status === CommitStatus::Pending ? ['toast' => [
                'type' => 'info',
                'message' => __('Your commit is pending review. Everyone will see it once it is approved.'),
            ]] : [],
        ], 201);
    }

    public function destroy(DeleteCommitReplyRequest $request, Commit $commit, Commit $reply, DeleteCommit $action): Response
    {
        $action->handle($reply);

        return response()->noContent();
    }
}
