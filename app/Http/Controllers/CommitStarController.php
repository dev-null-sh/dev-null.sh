<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitStar;
use App\Actions\DeleteCommitStar;
use App\Http\Requests\CreateCommitStarRequest;
use App\Http\Requests\DeleteCommitStarRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitStarController
{
    public function store(CreateCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitStar $action): JsonResponse
    {
        $action->handle($user, $commit);

        return response()->json([
            'starred' => true,
            'stars_count' => $commit->stars()->newPivotQuery()->count(),
        ]);
    }

    public function destroy(DeleteCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteCommitStar $action): JsonResponse
    {
        $action->handle($user, $commit);

        return response()->json([
            'starred' => false,
            'stars_count' => $commit->stars()->newPivotQuery()->count(),
        ]);
    }
}
