<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowMessageImageRequest;
use App\Models\MessageImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class MessageImageController
{
    public function show(ShowMessageImageRequest $request, MessageImage $messageImage): StreamedResponse
    {
        return Storage::disk()->response($messageImage->path, $messageImage->id.'.webp', headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], disposition: $request->download() ? 'attachment' : 'inline');
    }
}
