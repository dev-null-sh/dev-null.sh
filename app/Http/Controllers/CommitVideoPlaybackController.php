<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitVideoPlaybackRequest;
use App\Models\CommitVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class CommitVideoPlaybackController
{
    public function show(ShowCommitVideoPlaybackRequest $request, CommitVideo $commitVideo): RedirectResponse|BinaryFileResponse
    {
        $poster = $request->boolean('poster');
        $path = $commitVideo->path($poster ? 'poster.jpg' : 'video.mp4');

        // The local disk's temporary URL streams the whole file without byte-range support.
        if (config('filesystems.disks.'.config()->string('filesystems.default').'.driver') === 'local') {
            return response()->file(Storage::path($path), [
                'Content-Type' => $poster ? 'image/jpeg' : 'video/mp4',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return redirect()->away(Storage::temporaryUrl($path, now()->addMinutes(5)))
            ->header('Cache-Control', 'private, no-store');
    }
}
