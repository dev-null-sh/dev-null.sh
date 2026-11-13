<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitLaunchRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LaunchPassengersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitLaunchController
{
    public function show(ShowCommitLaunchRequest $request, #[CurrentUser] ?User $user, Commit $commit, LaunchPassengersQuery $passengers): JsonResponse
    {
        return response()->json($passengers->get($commit, $user))->header('Cache-Control', 'private, no-store');
    }
}
