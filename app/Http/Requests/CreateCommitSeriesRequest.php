<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Actions\CreateCommitSeries;
use App\Services\Urls;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\NestedRules;
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
     * @return array<string, array<int, string|File|Dimensions>|NestedRules>
     */
    public function rules(): array
    {
        return [
            'bodies' => ['required', 'list', 'min:2', 'max:'.CreateCommitSeries::MAX_PARTS],
            'bodies.*' => ['required', 'string', 'min:3', 'max:280'],
            'link_preview_urls' => ['nullable', 'array', 'max:'.CreateCommitSeries::MAX_PARTS],
            'link_preview_urls.*' => Rule::forEach(function (mixed $value, string $attribute): array {
                $index = Str::after($attribute, 'link_preview_urls.');
                $body = $this->input("bodies.{$index}");

                return ['nullable', 'string', Rule::in(resolve(Urls::class)->all(is_string($body) ? $body : ''))];
            }),
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link_preview_urls.*.in' => __('The link card must be one of the links in the body.'),
        ];
    }

    /**
     * @return array<int, bool|string>
     */
    public function linkPreviews(): array
    {
        $previews = [];

        foreach (array_keys($this->bodies()) as $index) {
            $key = "link_preview_urls.{$index}";

            if ($this->has($key)) {
                $previews[$index] = $this->filled($key) ? $this->string($key)->value() : false;
            }
        }

        return $previews;
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
