<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowMessageImageRequest;
use App\Models\MessageImage;
use App\Services\EncryptedFiles;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

final readonly class MessageImageController
{
    public function show(ShowMessageImageRequest $request, MessageImage $messageImage, EncryptedFiles $encryptedFiles): Response
    {
        return response($encryptedFiles->decrypt((string) Storage::disk()->get($messageImage->path)), headers: [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->download() ? 'attachment' : 'inline', $messageImage->id.'.webp'),
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
