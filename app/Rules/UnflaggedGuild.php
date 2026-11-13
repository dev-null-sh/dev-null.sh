<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\Services\ModerationProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\Image;
use Illuminate\Validation\Validator;

final readonly class UnflaggedGuild
{
    private const int MODERATED_ICON_DIMENSION = 512;

    private const int MODERATED_ICON_QUALITY = 60;

    public function __construct(private ModerationProvider $moderation)
    {
        //
    }

    public function validate(Validator $validator, string $text, ?UploadedFile $icon): void
    {
        try {
            $icons = $icon instanceof UploadedFile ? [$this->dataUrl($icon)] : [];
        } catch (ImageException) {
            $validator->errors()->add('images', 'The icon must be a JPEG, PNG, or WebP image.');

            return;
        }

        if ($this->moderation->moderate($text, $icons) !== [] || $this->moderation->isHarmful($text)) {
            $validator->errors()->add('description', 'This guild cannot be published. Change its name, its description or its icon.');
        }
    }

    private function dataUrl(UploadedFile $icon): string
    {
        $webp = Image::fromUpload($icon)
            ->usingGd()
            ->orient()
            ->scale(self::MODERATED_ICON_DIMENSION, self::MODERATED_ICON_DIMENSION)
            ->optimize('webp', self::MODERATED_ICON_QUALITY)
            ->toBytes();

        return 'data:image/webp;base64,'.base64_encode($webp);
    }
}
