<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\UploadedFile;
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
     * Display profiles run to hundreds of bytes, so a megabyte is generous; anything larger is not carried.
     */
    private const int MAX_PROFILE_BYTES = 1_048_576;

    /**
     * Every image is decoded, scaled down, and re-encoded as WebP: metadata such as GPS location is dropped, and anything smuggled alongside the pixels is not kept.
     * The one exception is the color profile, which says how the pixels are to be read; a plausible display profile is carried over so colors show as authored.
     * All of them are encoded before any is stored, so a bad one leaves nothing behind, and a stored one is erased again if the transaction saving it rolls back.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $descriptions
     * @return list<array{path: string, width: int, height: int, description: string|null, position: int}>
     */
    public function handle(array $images, array $descriptions = [], string $directory = 'commit-images'): array
    {
        $optimized = array_map($this->optimize(...), array_slice($images, 0, CreateCommitImages::MAX_IMAGES));

        $files = [];

        foreach ($optimized as $position => $image) {
            $path = $directory.'/'.Str::uuid7()->toString().'.webp';

            Storage::disk()->put($path, $image['bytes']);

            DB::afterRollBack(fn (): bool => Storage::disk()->delete($path));

            $files[] = [
                'path' => $path,
                'width' => $image['width'],
                'height' => $image['height'],
                'description' => $descriptions[$position] ?? null,
                'position' => $position,
            ];
        }

        return $files;
    }

    /**
     * GD is used explicitly since, unlike Imagick, it never writes the source's metadata back out.
     *
     * @return array{bytes: string, width: int, height: int}
     */
    private function optimize(UploadedFile $file): array
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
            $bytes = $image->toBytes();
        } catch (ImageException) {
            throw $this->invalid();
        }

        $profile = $this->colorProfile((string) file_get_contents($file->getRealPath()), $info[2]);

        return [
            'bytes' => $profile === null ? $bytes : $this->withColorProfile($bytes, $profile, $image->width(), $image->height()),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    private function colorProfile(string $contents, int $type): ?string
    {
        assert(in_array($type, self::ALLOWED_TYPES, true));

        return match ($type) {
            IMAGETYPE_JPEG => $this->jpegColorProfile($contents),
            IMAGETYPE_PNG => $this->pngColorProfile($contents),
            IMAGETYPE_WEBP => $this->webpColorProfile($contents),
        };
    }

    /**
     * A profile too large for one APP2 segment arrives numbered, in pieces.
     */
    private function jpegColorProfile(string $contents): ?string
    {
        $offset = 2;
        $pieces = [];

        while ($offset + 4 <= mb_strlen($contents, '8bit') && $contents[$offset] === "\xFF" && ! in_array(ord($contents[$offset + 1]), [0xD9, 0xDA], true)) {
            $segmentLength = ord($contents[$offset + 2]) * 256 + ord($contents[$offset + 3]);
            $segment = mb_substr($contents, $offset + 4, $segmentLength - 2, '8bit');

            if ($contents[$offset + 1] === "\xE2" && str_starts_with($segment, "ICC_PROFILE\0") && mb_strlen($segment, '8bit') > 14) {
                $pieces[ord($segment[12])] = mb_substr($segment, 14, null, '8bit');
            }

            $offset += $segmentLength + 2;
        }

        ksort($pieces);

        return $this->validatedColorProfile(implode('', $pieces));
    }

    private function pngColorProfile(string $contents): ?string
    {
        $offset = 8;

        while ($offset + 8 <= mb_strlen($contents, '8bit')) {
            $size = ord($contents[$offset]) * 16_777_216 + ord($contents[$offset + 1]) * 65_536 + ord($contents[$offset + 2]) * 256 + ord($contents[$offset + 3]);

            if (mb_substr($contents, $offset + 4, 4, '8bit') === 'iCCP') {
                $compressed = explode("\0", mb_substr($contents, $offset + 8, $size, '8bit'), 2)[1] ?? '';

                return $this->validatedColorProfile((string) @gzuncompress(mb_substr($compressed, 1, null, '8bit'), self::MAX_PROFILE_BYTES + 1));
            }

            $offset += $size + 12;
        }

        return null;
    }

    private function webpColorProfile(string $contents): ?string
    {
        $offset = 12;

        while ($offset + 8 <= mb_strlen($contents, '8bit')) {
            $size = ord($contents[$offset + 4]) + ord($contents[$offset + 5]) * 256 + ord($contents[$offset + 6]) * 65_536 + ord($contents[$offset + 7]) * 16_777_216;

            if (mb_substr($contents, $offset, 4, '8bit') === 'ICCP') {
                return $this->validatedColorProfile(mb_substr($contents, $offset + 8, $size, '8bit'));
            }

            $offset += $size + 8 + $size % 2;
        }

        return null;
    }

    /**
     * Only a plausible display profile is carried over; anything else could be data hiding beside the pixels.
     */
    private function validatedColorProfile(string $profile): ?string
    {
        $valid = mb_strlen($profile, '8bit') >= 128
            && mb_strlen($profile, '8bit') <= self::MAX_PROFILE_BYTES
            && ord($profile[0]) * 16_777_216 + ord($profile[1]) * 65_536 + ord($profile[2]) * 256 + ord($profile[3]) === mb_strlen($profile, '8bit')
            && mb_substr($profile, 12, 4, '8bit') === 'mntr'
            && mb_substr($profile, 16, 4, '8bit') === 'RGB '
            && mb_substr($profile, 36, 4, '8bit') === 'acsp';

        return $valid ? $profile : null;
    }

    /**
     * GD writes no color profile, so the validated one is muxed into the WebP container around its output:
     * an image with alpha already has the extended header that can declare it, a plain one gains it.
     */
    private function withColorProfile(string $webp, string $profile, int $width, int $height): string
    {
        if (mb_substr($webp, 12, 4, '8bit') === 'VP8X') {
            $webp[20] = chr(ord($webp[20]) | 0x20);
            $webp = substr_replace($webp, $this->webpChunk('ICCP', $profile), 30, 0);
        } else {
            $extended = "\x20\0\0\0".mb_substr(pack('V', $width - 1), 0, 3, '8bit').mb_substr(pack('V', $height - 1), 0, 3, '8bit');
            $webp = substr_replace($webp, $this->webpChunk('VP8X', $extended).$this->webpChunk('ICCP', $profile), 12, 0);
        }

        return substr_replace($webp, pack('V', mb_strlen($webp, '8bit') - 8), 4, 4);
    }

    private function webpChunk(string $type, string $data): string
    {
        return $type.pack('V', mb_strlen($data, '8bit')).$data.(mb_strlen($data, '8bit') % 2 === 1 ? "\x00" : '');
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'images' => 'Images must be JPEG, PNG, or WebP files of at most '.CreateCommitImages::MAX_SOURCE_DIMENSION.' pixels per side.',
        ]);
    }
}
