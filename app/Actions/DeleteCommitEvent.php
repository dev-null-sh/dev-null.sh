<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteCommitEvent
{
    /**
     * Only a commit whose undo window has closed loses its event and cover, so one restored in time, or deleted again since, keeps them.
     */
    public function handle(Commit $commit): void
    {
        $coverPath = DB::transaction(function () use ($commit): ?string {
            $commit = Commit::withTrashed()->lockForUpdate()->find($commit->id);

            if (! $commit instanceof Commit || ! $commit->trashed() || $commit->isRestorable()) {
                return null;
            }

            $coverPath = $commit->event?->cover_path;

            $commit->event?->delete();

            return $coverPath;
        });

        if ($coverPath !== null) {
            Storage::disk()->delete($coverPath);
        }
    }
}
