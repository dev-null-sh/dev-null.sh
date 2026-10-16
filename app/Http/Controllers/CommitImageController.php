<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitImageRequest;
use App\Models\CommitImage;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class CommitImageController
{
    public function show(ShowCommitImageRequest $request, CommitImage $commitImage): BinaryFileResponse|RedirectResponse
    {
        $disk = Storage::disk();

        if (! $disk instanceof LocalFilesystemAdapter) {
            return redirect()
                ->away($commitImage->publicUrl(preview: $request->isPoster()))
                ->withHeaders(['Cache-Control' => 'private, no-store']);
        }

        return response()->file($disk->path($request->mediaPath()), [
            'Content-Type' => $request->isPoster() ? 'image/webp' : $commitImage->mime_type,
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ])->setPrivate();
    }
}
