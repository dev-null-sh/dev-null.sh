<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Actions\CreateCommitSeries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;

final class CreateCommitSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Images go with the first part of the series, checked like those of a single commit: by their contents, not their extension, and never SVG.
     *
     * @return array<string, array<int, string|File|Dimensions>>
     */
    public function rules(): array
    {
        return [
            'bodies' => ['required', 'list', 'min:2', 'max:'.CreateCommitSeries::MAX_PARTS],
            'bodies.*' => ['required', 'string', 'min:3', 'max:280'],
            'images' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'images.*' => [
                'required',
                'image',
                File::types(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])->max(CreateCommitImages::MAX_UPLOAD_SIZE),
                Rule::dimensions()
                    ->maxWidth(CreateCommitImages::MAX_SOURCE_DIMENSION)
                    ->maxHeight(CreateCommitImages::MAX_SOURCE_DIMENSION),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bodies.*' => 'commit',
        ];
    }

    /**
     * @return list<string>
     */
    public function bodies(): array
    {
        /** @var list<string> $bodies */
        $bodies = $this->validated('bodies');

        return $bodies;
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }
}
