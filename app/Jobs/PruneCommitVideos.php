<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DeleteCommitVideo;
use App\Enums\CommitVideoStatus;
use App\Models\CommitVideo;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\StorageAttributes;

final class PruneCommitVideos implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(DeleteCommitVideo $delete): void
    {
        $cutoff = now()->subDay();
        // Signed upload URLs expire after 15 minutes, so unfinished uploads never need an hour.
        $uploadCutoff = now()->subHour();

        CommitVideo::query()->whereNull('commit_id')
            ->where(fn (Builder $query): Builder => $query->where('created_at', '<', $cutoff)
                ->orWhere(fn (Builder $query): Builder => $query->whereIn('status', [CommitVideoStatus::Uploading, CommitVideoStatus::Failed])->where('created_at', '<', $uploadCutoff)))
            ->eachById($delete->handle(...));

        // Signed PUTs may arrive after cancellation deleted the row. Files whose row still exists wait for that row's own cleanup.
        $orphans = collect(Storage::disk()->listContents('video-uploads')->toArray())
            ->filter(fn (StorageAttributes $entry): bool => $entry->isFile() && Storage::lastModified($entry->path()) < $uploadCutoff->getTimestamp())
            ->map(fn (StorageAttributes $entry): string => $entry->path());
        $kept = CommitVideo::query()->whereKey($orphans->map(fn (string $path): string => basename($path))->filter(fn (string $id): bool => Str::isUuid($id))->values())->pluck('id');

        Storage::delete($orphans->reject(fn (string $path): bool => $kept->contains(basename($path)))->values()->all());

        $scratch = storage_path('app/video-processing');

        if (File::isDirectory($scratch)) {
            foreach (glob($scratch.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
                if (File::lastModified($directory) < $cutoff->getTimestamp()) {
                    File::deleteDirectory($directory);
                }
            }
        }
    }
}
