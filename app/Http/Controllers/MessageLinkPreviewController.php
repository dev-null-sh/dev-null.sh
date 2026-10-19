<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowMessageLinkPreviewRequest;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

final readonly class MessageLinkPreviewController
{
    public function show(ShowMessageLinkPreviewRequest $request, Message $message): JsonResponse
    {
        return response()->json([
            'link_preview' => $message->linkPreview,
        ])->header('Cache-Control', 'private, no-store');
    }
}
