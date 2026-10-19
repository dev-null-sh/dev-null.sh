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
        /** @var list<string> $repositories */
        $repositories = $request->validated('repositories', []);
        $action->handle($user, $commit, $request->flavor(), $request->boolean('public', true), $repositories);

        return response()->json(Commit::query()->withStarsFor($user)->findOrFail($commit->id)->only(['starred', 'stars_count', 'star_flavor', 'star_flavors', 'reactions']));
    }

    public function destroy(DeleteCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteCommitStar $action): JsonResponse
    {
        $action->handle($user, $commit, $request->has('flavor') ? $request->string('flavor')->value() : null);

        return response()->json(Commit::query()->withStarsFor($user)->findOrFail($commit->id)->only(['starred', 'stars_count', 'star_flavor', 'star_flavors', 'reactions']));
    }
}
