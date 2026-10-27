<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListRelatedCommitsRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\RelatedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class RelatedCommitController
{
    public function index(ListRelatedCommitsRequest $request, #[CurrentUser] ?User $user, Commit $commit, RelatedCommitsQuery $related): JsonResponse
    {
        return response()->json(['commits' => $related->get($commit, $user)]);
    }
}
