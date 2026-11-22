<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitVideo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteCommitVideo
{
    public function handle(CommitVideo $video): void
    {
        DB::transaction(function () use ($video): void {
            $video = CommitVideo::query()->whereNull('commit_id')->lockForUpdate()->find($video->id);

            if ($video !== null) {
                Storage::deleteDirectory('commit-videos/'.$video->id);
                Storage::delete($video->path('source'));
                $video->delete();
            }
        });
    }
}
