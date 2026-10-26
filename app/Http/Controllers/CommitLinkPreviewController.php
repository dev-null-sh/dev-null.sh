<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitLinkPreviewRequest;
use App\Http\Resources\LinkPreviewResource;
use App\Models\Commit;
use Illuminate\Http\JsonResponse;

final readonly class CommitLinkPreviewController
{
    public function show(ShowCommitLinkPreviewRequest $request, Commit $commit): JsonResponse
    {
        return response()->json([
            'link_preview' => LinkPreviewResource::maybe($commit->linkPreview),
        ])->header('Cache-Control', 'private, no-store');
    }
}
