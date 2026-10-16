<?php

declare(strict_types=1);

namespace App\Rules;

use App\Actions\CreateCommitImages;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class CommitMedia implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param Closure(int, array{file: UploadedFile, width: int, height: int, poster: ProcessedImage}): void $acceptAnimation */
    public function __construct(private readonly Closure $acceptAnimation)
    {
        //
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || Validator::make(['media' => $value], [
            'media' => [File::types(['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/webm'])
                ->max(CreateCommitImages::MAX_UPLOAD_SIZE)],
        ])->fails()) {
            $fail(__('Attachments must be valid files up to 10 MB.'));

            return;
        }
        if ($value->getMimeType() === 'video/webm') {
            $this->validateAnimation($attribute, $value, $fail);

            return;
        }

        $this->validateImage($value, $fail);
    }

    /** Video timing and track limits are enforced by the browser; stored dimensions describe the poster. */
    private function validateAnimation(string $attribute, UploadedFile $value, Closure $fail): void
    {
        $position = (int) Str::afterLast($attribute, '.');
        $upload = data_get($this->data, "image_posters.{$position}");

        if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
            $fail(__('The animation preview is missing. Please attach the GIF again.'));

            return;
        }

        if (Validator::make(['poster' => $upload], [
            'poster' => [
                'image',
                File::types(['png', 'webp'])->max('5mb'),
                Rule::dimensions()->maxWidth(960)->maxHeight(960),
            ],
        ])->fails()) {
            $fail(__('The animation preview must be a PNG or WebP up to 5 MB and 960 pixels per side.'));

            return;
        }

        try {
            $poster = Image::fromUpload($upload)->usingGd()->optimize('webp', 60);
            $poster->toBytes();
        } catch (ImageException) {
            $fail(__('The animation preview cannot be decoded. Please attach the GIF again.'));

            return;
        }

        ($this->acceptAnimation)($position, [
            'file' => $value,
            'width' => $poster->width(),
            'height' => $poster->height(),
            'poster' => $poster,
        ]);
    }

    private function validateImage(UploadedFile $value, Closure $fail): void
    {
        if (Validator::make(['image' => $value], [
            'image' => [File::image()->dimensions(
                Rule::dimensions()
                    ->maxWidth(CreateCommitImages::MAX_SOURCE_DIMENSION)
                    ->maxHeight(CreateCommitImages::MAX_SOURCE_DIMENSION),
            )],
        ])->fails()) {
            $fail(__('Attach JPEG, PNG, GIF, or WebP images, or GIF animations converted by this app.'));
        }
    }
}
