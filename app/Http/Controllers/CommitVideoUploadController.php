<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitVideoUpload;
use App\Http\Requests\UpdateCommitVideoUploadRequest;
use App\Models\CommitVideo;
use Illuminate\Http\Response;

final readonly class CommitVideoUploadController
{
    public function update(UpdateCommitVideoUploadRequest $request, CommitVideo $commitVideo, UpdateCommitVideoUpload $action): Response
    {
        $action->handle($commitVideo, $request->getContent(true));

        return response()->noContent();
    }
}
