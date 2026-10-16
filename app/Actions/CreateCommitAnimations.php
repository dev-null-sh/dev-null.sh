<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class CreateCommitAnimations
{
    private const string CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /** @param array{file: UploadedFile, width: int, height: int, poster: ProcessedImage} $animation */
    public function handle(Commit $commit, array $animation, ?string $description, int $position): void
    {
        $image = $commit->images()->make([
            'path' => 'commit-images/'.Str::uuid7()->toString().'.webm',
            'width' => $animation['width'],
            'height' => $animation['height'],
            'mime_type' => 'video/webm',
            'description' => $description,
            'position' => $position,
        ]);

        try {
            $this->storeVideo($image, $animation['file']);
            $this->storePoster($image, $animation['poster']);
            $image->save();
        } catch (Throwable $exception) {
            Storage::delete([$image->path, $image->previewPath()]);
            throw $exception;
        }

        $disk = Storage::disk();
        DB::afterRollBack(fn () => $disk->delete([$image->path, $image->previewPath()]));
    }

    private function storeVideo(CommitImage $image, UploadedFile $file): void
    {
        $this->storeFile($image->path, $file->getContent(), 'video/webm');
    }

    private function storePoster(CommitImage $image, ProcessedImage $poster): void
    {
        $this->storeFile($image->previewPath(), $poster->toBytes(), 'image/webp');
    }

    private function storeFile(string $path, string $contents, string $contentType): void
    {
        if (! Storage::put($path, $contents, [
            'ContentType' => $contentType,
            'ContentDisposition' => 'inline',
            'CacheControl' => self::CACHE_CONTROL,
        ])) {
            throw new RuntimeException('Unable to store animation file.');
        }
    }
}
