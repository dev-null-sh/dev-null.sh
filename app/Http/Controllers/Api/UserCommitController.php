<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiUserCommitsRequest;
use App\Http\Resources\CommitResource;
use App\Models\User;
use App\Queries\CommitBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserCommitController
{
    public function index(ListApiUserCommitsRequest $request, User $user): AnonymousResourceCollection
    {
        return CommitResource::collection($user->commits()
            ->approved()
            ->when(
                $request->replies(),
                fn (CommitBuilder $query): CommitBuilder => $query->whereNotNull('parent_id')->exceptSeriesContinuations(),
                fn (CommitBuilder $query): CommitBuilder => $query->topLevel(),
            )
            ->withPublicPreview()
            ->latest()
            ->latest('id')
            ->cursorPaginate(20)
            ->appends($request->only('replies')));
    }
}
