<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitVideoProcessing;
use App\Http\Requests\CreateCommitVideoProcessingRequest;
use App\Models\CommitVideo;
use Illuminate\Http\JsonResponse;

final readonly class CommitVideoProcessingController
{
    public function store(CreateCommitVideoProcessingRequest $request, CommitVideo $commitVideo, UpdateCommitVideoProcessing $action): JsonResponse
    {
        return response()->json(['video' => $action->handle($commitVideo)]);
    }
}
