<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Actions\UpdateUserCoverImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;

final class UpdateUserCoverImageRequest extends FormRequest
{
    /**
     * Smaller sources would be blown up into a blurry banner, and larger ones are refused before they are decoded.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions>>
     */
    public function rules(): array
    {
        return [
            'cover_image' => [
                'required',
                'image',
                File::types(['image/jpeg', 'image/png', 'image/webp'])->max(UpdateUserCoverImage::MAX_UPLOAD_SIZE),
                Rule::dimensions()
                    ->minWidth(UpdateUserCoverImage::WIDTH / 2)
                    ->minHeight(UpdateUserCoverImage::HEIGHT / 2)
                    ->maxWidth(CreateCommitImages::MAX_SOURCE_DIMENSION)
                    ->maxHeight(CreateCommitImages::MAX_SOURCE_DIMENSION),
            ],
            'offset_x' => ['required', 'numeric', 'between:0,1'],
            'offset_y' => ['required', 'numeric', 'between:0,1'],
        ];
    }

    public function coverImage(): UploadedFile
    {
        $file = $this->file('cover_image');
        assert($file instanceof UploadedFile);

        return $file;
    }
}
