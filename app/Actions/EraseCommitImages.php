<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class EraseCommitImages
{
    /**
     * Only a commit whose undo window has closed loses its images, so one restored in time, or deleted again since, keeps them.
     */
    public function handle(Commit $commit): void
    {
        $imagePaths = DB::transaction(function () use ($commit): array {
            $commit = Commit::withTrashed()->lockForUpdate()->find($commit->id);

            if (! $commit instanceof Commit
                || $commit->deleted_at === null
                || now()->lessThan($commit->deleted_at->copy()->addSeconds(RestoreCommit::RECOVERY_SECONDS))) {
                return [];
            }

            $imagePaths = $commit->images()->pluck('path')->all();

            $commit->images()->delete();

            return $imagePaths;
        });

        Storage::disk(CommitImage::DISK)->delete($imagePaths);
    }
}
