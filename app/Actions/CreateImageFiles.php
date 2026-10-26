<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\EncryptedFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateImageFiles
{
    private const int MAX_STORED_DIMENSION = 1600;

    private const int QUALITY = 60;

    private const int MAX_GIF_PIXELS = 8_000_000;

    private const array ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

    public function __construct(private EncryptedFiles $encryptedFiles)
    {
        //
    }

    /**
     * Every image is decoded, scaled down, and re-encoded as WebP: metadata such as GPS location is dropped, and anything smuggled alongside the pixels is not kept.
     * All of them are encoded before any is stored, so a bad one leaves nothing behind, and a stored one is erased again if the transaction saving it rolls back.
     * Private images, such as the ones sent in remotes, are stored encrypted.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $descriptions
     * @return list<array{path: string, width: int, height: int, description: string|null, position: int}>
     */
    public function handle(array $images, array $descriptions = [], string $directory = 'commit-images', bool $encrypted = false): array
    {
        $optimized = array_map($this->optimize(...), array_slice($images, 0, CreateCommitImages::MAX_IMAGES));

        $files = [];

        foreach ($optimized as $position => $image) {
            if ($encrypted) {
                $path = $directory.'/'.Str::uuid7()->toString().($image->extension() === 'gif' ? '.gif' : '');
                $stored = Storage::disk()->put($path, $this->encryptedFiles->encrypt($image->toBytes()));
                assert($stored);
            } else {
                $path = $image->storeAs($directory, Str::uuid7()->toString().'.'.$image->extension());
                assert(is_string($path));
            }

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
            || $info[1] > CreateCommitImages::MAX_SOURCE_DIMENSION
            || ($info[2] === IMAGETYPE_GIF && max($info[0] * $info[1], 16_000) * mb_substr_count((string) file_get_contents($file->getRealPath()), "\x00\x2C") > self::MAX_GIF_PIXELS)) {
            throw $this->invalid();
        }

        $image = Image::fromUpload($file)
            ->usingGd()
            ->orient()
            ->scale(self::MAX_STORED_DIMENSION, self::MAX_STORED_DIMENSION)
            ->optimize($info[2] === IMAGETYPE_GIF ? 'gif' : 'webp', self::QUALITY);

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
            'images' => 'Images must be JPEG, PNG, WebP, or GIF files of at most '.CreateCommitImages::MAX_SOURCE_DIMENSION.' pixels per side, and GIFs not too long.',
        ]);
    }
}
