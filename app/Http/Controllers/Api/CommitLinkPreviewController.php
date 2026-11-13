<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiCommitLinkPreviewRequest;
use App\Models\Commit;
use Illuminate\Http\JsonResponse;

final readonly class CommitLinkPreviewController
{
    /**
     * A commit's link card, which the web asks for again while it is still pending.
     */
    public function show(ShowApiCommitLinkPreviewRequest $request, Commit $commit): JsonResponse
    {
        return response()->json([
            'link_preview' => $commit->linkPreview,
        ])->header('Cache-Control', 'private, no-store');
    }
}
