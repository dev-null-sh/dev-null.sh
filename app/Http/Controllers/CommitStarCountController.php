<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitStarCountRequest;
use App\Models\User;
use App\Queries\CommitStarCountsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitStarCountController
{
    public function index(ListCommitStarCountRequest $request, #[CurrentUser] User $user, CommitStarCountsQuery $stars): JsonResponse
    {
        return response()->json([
            'commits' => $stars->get($user, $request->ids()),
        ])->header('Cache-Control', 'private, no-store');
    }
}
