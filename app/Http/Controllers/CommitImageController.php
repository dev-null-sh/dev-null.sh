<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitImageRequest;
use App\Models\CommitImage;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CommitImageController
{
    /**
     * How long a redirect to cloud storage may be reused, kept well inside the life of the signed URL it points at.
     */
    private const int REDIRECT_MAX_AGE = 3600;

    public function show(ShowCommitImageRequest $request, CommitImage $commitImage): RedirectResponse|StreamedResponse
    {
        $disk = Storage::disk();

        // Cloud storage serves the bytes itself, through a short-lived signed URL, once access has been checked here.
        if ($disk instanceof AwsS3V3Adapter) {
            $url = $disk->temporaryUrl($commitImage->path, now()->addSeconds(self::REDIRECT_MAX_AGE * 2), [
                'ResponseContentType' => 'image/webp',
                'ResponseContentDisposition' => 'inline',
                'ResponseCacheControl' => 'private, max-age='.self::REDIRECT_MAX_AGE,
            ]);

            return redirect()->away($url)->withHeaders([
                'Cache-Control' => 'private, max-age='.self::REDIRECT_MAX_AGE,
            ]);
        }

        return $disk->response($commitImage->path, $commitImage->id.'.webp', headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], disposition: $request->download() ? 'attachment' : 'inline');
    }
}
