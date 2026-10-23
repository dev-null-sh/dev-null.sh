<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitVideo;
use App\Models\LinkPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteCommitContent
{
    /**
     * Only a commit whose undo window has closed loses its content, so one restored in time, or deleted again since, keeps it.
     * The row stays as a tombstone with its author, for its replies, quotes and reports, but its body, mentions, embedding,
     * link preview, poll and images are erased for good. So are the previews of links to it, which hold its body as their
     * title and description, and fall off whatever linked to it.
     */
    public function handle(Commit $commit): void
    {
        $imagePaths = DB::transaction(function () use ($commit): array {
            $commit = Commit::withTrashed()->lockForUpdate()->find($commit->id);

            if (! $commit instanceof Commit || ! $commit->trashed() || $commit->isRestorable()) {
                return [];
            }

            $imagePaths = $commit->images()->pluck('path')->all();

            CommitVideo::query()->where('commit_id', $commit->id)->lockForUpdate()->each(function (CommitVideo $video): void {
                Storage::delete($video->path('source'));
                Storage::deleteDirectory('commit-videos/'.$video->id);
                $video->delete();
            });

            $commit->images()->delete();
            $commit->poll()->delete();

            LinkPreview::query()->where('url', 'like', "%/commits/{$commit->id}%")->delete();

            $commit->update(['body' => '', 'mentions' => null, 'embedding' => null, 'link_preview_id' => null]);

            return $imagePaths;
        });

        Storage::disk()->delete($imagePaths);
    }
}
