<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateCommitImageFiles
{
    private const int MAX_STORED_DIMENSION = 1600;

    private const int QUALITY = 60;

    private const array ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * Every image is decoded, scaled down, and re-encoded as WebP: metadata such as GPS location is dropped, and anything smuggled alongside the pixels is not kept.
     * All of them are encoded before any is stored, so a bad one leaves nothing behind, and a stored one is erased again if the transaction saving it rolls back.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $descriptions
     * @return list<array{path: string, width: int, height: int, description: string|null, position: int}>
     */
    public function handle(array $images, array $descriptions = []): array
    {
        $optimized = array_map($this->optimize(...), array_slice($images, 0, CreateCommitImages::MAX_IMAGES));

        $files = [];

        foreach ($optimized as $position => $image) {
            $path = $image->storeAs('commit-images', Str::uuid7()->toString().'.webp');
            assert(is_string($path));

            DB::afterRollBack(fn (): bool => Storage::disk()->delete($path));

            $files[] = [
                'path' => $path,
                'width' => $image->width(),
                'height' => $image->height(),
                'description' => $descriptions[$position] ?? null,
                'position' => $position,
            ];
        }

        return $files;
    }

    /**
     * GD is used explicitly since, unlike Imagick, it never writes the source's metadata back out.
     */
    private function optimize(UploadedFile $file): ProcessedImage
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false
            || ! in_array($info[2], self::ALLOWED_TYPES, true)
            || $info[0] > CreateCommitImages::MAX_SOURCE_DIMENSION
            || $info[1] > CreateCommitImages::MAX_SOURCE_DIMENSION) {
            throw $this->invalid();
        }

        $image = Image::fromUpload($file)
            ->usingGd()
            ->orient()
            ->scale(self::MAX_STORED_DIMENSION, self::MAX_STORED_DIMENSION)
            ->optimize('webp', self::QUALITY);

        try {
            $image->toBytes();
        } catch (ImageException) {
            throw $this->invalid();
        }

        return $image;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'images' => __('Images must be JPEG, PNG, or WebP files of at most :size pixels per side.', ['size' => CreateCommitImages::MAX_SOURCE_DIMENSION]),
        ]);
    }
}
