<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListBranchMemberCandidatesRequest;
use App\Models\Branch;
use App\Models\User;
use App\Queries\BranchMemberCandidatesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class BranchMemberCandidateController
{
    public function index(
        ListBranchMemberCandidatesRequest $request,
        Branch $branch,
        #[CurrentUser] User $user,
        BranchMemberCandidatesQuery $candidates,
    ): JsonResponse {
        return response()->json([
            'candidates' => $candidates->get(
                $user,
                $branch,
                $request->string('q')->value(),
            ),
        ])->header('Cache-Control', 'private, no-store');
    }
}
