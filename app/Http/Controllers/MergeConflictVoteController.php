<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateMergeConflictVote;
use App\Http\Requests\CreateMergeConflictVoteRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\MergeConflictQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class MergeConflictVoteController
{
    public function store(CreateMergeConflictVoteRequest $request, #[CurrentUser] User $user, Commit $commit, CreateMergeConflictVote $action, MergeConflictQuery $mergeConflicts): JsonResponse
    {
        $action->handle($user, $request->mergeConflict(), $request->side());

        return response()->json([
            'mergeConflict' => $mergeConflicts->get($commit, $user),
        ]);
    }
}
