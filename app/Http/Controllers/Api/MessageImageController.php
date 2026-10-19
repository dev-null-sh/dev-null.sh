<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiMessageImageRequest;
use App\Models\MessageImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class MessageImageController
{
    /**
     * Serves an image from a signed URL a thread handed out, with the web's headers.
     */
    public function show(ShowApiMessageImageRequest $request, MessageImage $messageImage): StreamedResponse
    {
        return Storage::disk()->response($messageImage->path, $messageImage->id.'.webp', headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
