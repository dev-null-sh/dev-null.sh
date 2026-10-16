<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Services\Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
use Illuminate\Support\Str;

final readonly class CreateCommitImages
{
    public const int MAX_IMAGES = 3;

    public const int MAX_DESCRIPTION_LENGTH = 1000;

    public function __construct(private Images $images)
    {
        //
    }

    /**
     * Every image is decoded, scaled down, and re-encoded as WebP before it is stored.
     * All of them are encoded before any is stored, so a bad one leaves nothing behind.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $descriptions
     */
    public function handle(Commit $commit, array $images, array $descriptions = []): void
    {
        $optimized = array_map(
            fn (UploadedFile $file): ProcessedImage => $this->images->optimize($file, 'images'),
            array_slice($images, 0, self::MAX_IMAGES),
        );

        foreach ($optimized as $position => $image) {
            $path = $image->storeAs('commit-images', Str::uuid7()->toString().'.webp');

            $commit->images()->create([
                'path' => $path,
                'width' => $image->width(),
                'height' => $image->height(),
                'description' => $descriptions[$position] ?? null,
                'position' => $position,
            ]);
        }
    }
}
