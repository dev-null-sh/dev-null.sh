<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitPastel;
use App\Http\Requests\CreateCommitPastelRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitPastelController
{
    public function store(CreateCommitPastelRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitPastel $action): JsonResponse
    {
        $awarded = $action->handle($user, $commit);

        return response()->json([
            'pasteis_count' => $commit->refresh()->pasteis_count,
            'remaining' => $user->refresh()->pasteisRemaining(),
            'day' => today()->toDateString(),
            'recipient' => [
                'id' => $commit->user_id,
                'pasteis_received' => $commit->user?->pasteis_received,
            ],
            ...$awarded ? [] : ['errors' => ['pastel' => ['you ran out of pastéis. wait until tomorrow.']]],
        ], $awarded ? 200 : 422);
    }
}
