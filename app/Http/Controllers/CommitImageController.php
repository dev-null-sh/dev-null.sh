<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CommitImageController
{
    /**
     * Pending and rejected images never leave the server for anyone but their author and moderators.
     */
    public function show(#[CurrentUser] ?User $user, CommitImage $commitImage): StreamedResponse
    {
        $commit = Commit::query()->find($commitImage->commit_id);

        abort_unless($commit instanceof Commit && $commit->imagesAreVisibleTo($user), 404);

        return Storage::disk(CommitImage::DISK)->response($commitImage->path, headers: [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
