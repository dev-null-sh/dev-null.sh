<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowPublicCommitRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;

final readonly class CommitController
{
    public function show(ShowPublicCommitRequest $request, Commit $commit): CommitResource
    {
        return CommitResource::make(Commit::query()
            ->withPublicPreview()
            ->whereKey($commit->id)
            ->sole());
    }
}
