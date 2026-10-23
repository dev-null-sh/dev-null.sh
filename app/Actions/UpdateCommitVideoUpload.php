<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitVideo;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateCommitVideoUpload
{
    /** @param  resource  $contents */
    public function handle(CommitVideo $video, mixed $contents): void
    {
        Storage::put($video->path('source'), $contents);
    }
}
