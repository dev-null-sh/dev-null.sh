<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitVideo;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCommitVideo
{
    public function __construct(private DeleteStoredFiles $deleteFiles)
    {
        //
    }

    public function handle(CommitVideo $video): void
    {
        DB::transaction(function () use ($video): void {
            $video = CommitVideo::query()->whereNull('commit_id')->lockForUpdate()->find($video->id);

            if ($video !== null) {
                $this->deleteFiles->retain($video->paths());
                $video->delete();
            }
        });
    }
}
