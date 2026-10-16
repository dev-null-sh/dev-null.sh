<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteCommitImages
{
    /**
     * Only a commit whose undo window has closed loses its images, so one restored in time, or deleted again since, keeps them.
     */
    public function handle(Commit $commit): void
    {
        $imagePaths = DB::transaction(function () use ($commit): array {
            $commit = Commit::withTrashed()->lockForUpdate()->find($commit->id);

            if (! $commit instanceof Commit || ! $commit->trashed() || $commit->isRestorable()) {
                return [];
            }

            $imagePaths = $commit->images()->pluck('path')->all();

            $commit->images()->delete();

            return $imagePaths;
        });

        Storage::disk()->delete($imagePaths);
    }
}
