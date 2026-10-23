<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiMessageLinkPreviewRequest;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

final readonly class MessageLinkPreviewController
{
    /**
     * A message's link card, which the web asks for again while it is still pending.
     */
    public function show(ShowApiMessageLinkPreviewRequest $request, Message $message): JsonResponse
    {
        return response()->json([
            'link_preview' => $message->linkPreview,
        ]);
    }
}
