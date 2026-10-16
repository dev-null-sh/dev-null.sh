<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\Image;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

final readonly class Images
{
    public const string MAX_UPLOAD_SIZE = '10mb';

    /**
     * Larger sources are refused before they are decoded, so a tiny file cannot expand into gigabytes of pixels.
     */
    public const int MAX_SOURCE_DIMENSION = 6000;

    private const int MAX_STORED_DIMENSION = 1600;

    private const int QUALITY = 60;

    private const array ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    /**
     * Uploads are checked by their contents, not their extension, and SVG is never accepted since it can carry scripts.
     *
     * @return array<int, mixed>
     */
    public static function uploadRules(): array
    {
        return [
            'image',
            File::types(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])->max(self::MAX_UPLOAD_SIZE),
            Rule::dimensions()->maxWidth(self::MAX_SOURCE_DIMENSION)->maxHeight(self::MAX_SOURCE_DIMENSION),
        ];
    }

    /**
     * Decodes, scales down, and re-encodes the image as WebP: metadata such as GPS location is dropped, and anything smuggled alongside the pixels is not kept.
     * GD is used explicitly since, unlike Imagick, it never writes the source's metadata back out.
     */
    public function optimize(UploadedFile $file, string $attribute): ProcessedImage
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false
            || ! in_array($info[2], self::ALLOWED_TYPES, true)
            || $info[0] > self::MAX_SOURCE_DIMENSION
            || $info[1] > self::MAX_SOURCE_DIMENSION) {
            throw $this->invalid($attribute);
        }

        $image = Image::fromUpload($file)
            ->usingGd()
            ->orient()
            ->scale(self::MAX_STORED_DIMENSION, self::MAX_STORED_DIMENSION)
            ->optimize('webp', self::QUALITY);

        try {
            $image->toBytes();
        } catch (ImageException) {
            throw $this->invalid($attribute);
        }

        return $image;
    }

    private function invalid(string $attribute): ValidationException
    {
        return ValidationException::withMessages([
            $attribute => __('Images must be JPEG, PNG, GIF, or WebP files of at most :size pixels per side.', ['size' => self::MAX_SOURCE_DIMENSION]),
        ]);
    }
}
