<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitContinuationRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitContinuationQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitContinuationController
{
    public function index(ListCommitContinuationRequest $request, #[CurrentUser] ?User $user, Commit $commit, CommitContinuationQuery $continuation): JsonResponse
    {
        return response()->json($continuation->get($commit, $user));
    }
}
