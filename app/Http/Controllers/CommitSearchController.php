<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitSearchRequest;
use App\Models\User;
use App\Queries\CommitSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitSearchController
{
    public function index(ListCommitSearchRequest $request, #[CurrentUser] User $user, CommitSearchQuery $search): JsonResponse
    {
        return response()->json([
            'commits' => $search->get($request->string('q')->value(), $user, $request->limit()),
        ]);
    }
}
