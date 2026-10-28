<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowCommitProofRequest;
use App\Models\Commit;
use App\Services\CommitProofs;
use Illuminate\Http\JsonResponse;

final readonly class CommitProofController
{
    public function show(ShowCommitProofRequest $request, Commit $commit, CommitProofs $proofs): JsonResponse
    {
        return response()->json($proofs->get($commit))->header('Cache-Control', 'no-store');
    }
}
