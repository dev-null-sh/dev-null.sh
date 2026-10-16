<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use UnexpectedValueException;

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

            $imagePaths = $commit->images()
                ->pluck('mime_type', 'path')
                ->flatMap(fn (mixed $mimeType, int|string $path): array => match ($mimeType) {
                    'video/webm' => [$path, $path.'.poster.webp'],
                    'image/webp' => [$path],
                    default => throw new UnexpectedValueException('Unsupported stored media type.'),
                })
                ->all();

            $commit->images()->delete();

            return $imagePaths;
        });

        Storage::disk()->delete($imagePaths);
    }
}
