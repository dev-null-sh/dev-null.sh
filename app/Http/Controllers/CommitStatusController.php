<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitStatusRequest;
use App\Models\Commit;
use Illuminate\Http\JsonResponse;

final readonly class CommitStatusController
{
    /**
     * A pending commit is checked in the background right after it is posted, so its author asks again until the check
     * is over, instead of only seeing the outcome after a reload.
     */
    public function show(ShowCommitStatusRequest $request, Commit $commit): JsonResponse
    {
        return response()->json([
            'status' => $commit->status,
            'is_checking' => $commit->isBeingChecked(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
