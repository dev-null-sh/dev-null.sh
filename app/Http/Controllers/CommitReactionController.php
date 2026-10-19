<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitReaction;
use App\Actions\DeleteCommitReaction;
use App\Http\Requests\CreateCommitReactionRequest;
use App\Http\Requests\DeleteCommitReactionRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitReactionController
{
    public function store(CreateCommitReactionRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitReaction $action): JsonResponse
    {
        /** @var list<string> $repositories */
        $repositories = $request->validated('repositories', []);
        $action->handle($user, $commit, $request->flavor(), $request->boolean('public', true), $repositories);

        return response()->json(Commit::query()->withStarsFor($user)->findOrFail($commit->id)->only(['starred', 'stars_count', 'star_flavor', 'star_flavors', 'reactions']));
    }

    public function destroy(DeleteCommitReactionRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteCommitReaction $action): JsonResponse
    {
        $action->handle($user, $commit, $request->has('flavor') ? $request->string('flavor')->value() : null);

        return response()->json(Commit::query()->withStarsFor($user)->findOrFail($commit->id)->only(['starred', 'stars_count', 'star_flavor', 'star_flavors', 'reactions']));
    }
}
