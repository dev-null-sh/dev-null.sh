<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitVideoStatus;
use App\Jobs\ProcessCommitVideo;
use App\Models\CommitVideo;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitVideoProcessing
{
    public function handle(CommitVideo $video): CommitVideo
    {
        return DB::transaction(function () use ($video): CommitVideo {
            $video = CommitVideo::query()->lockForUpdate()->findOrFail($video->id);

            if ($video->status === CommitVideoStatus::Uploading) {
                $video->update(['status' => CommitVideoStatus::Processing]);
                dispatch(new ProcessCommitVideo($video->id))->afterCommit();
            }

            return $video;
        });
    }
}
