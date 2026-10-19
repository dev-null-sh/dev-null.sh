<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListRepoContributorCandidatesRequest;
use App\Models\Repo;
use App\Models\User;
use App\Queries\RepoContributorCandidatesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class RepoContributorCandidateController
{
    public function index(ListRepoContributorCandidatesRequest $request, #[CurrentUser] User $user, Repo $repo, RepoContributorCandidatesQuery $candidates): JsonResponse
    {
        return response()->json([
            'candidates' => $candidates->get($user, $request->string('q')->value(), $repo),
        ])->header('Cache-Control', 'private, no-store');
    }
}
