<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateIssueRequest extends FormRequest
{
    public const array ENVIRONMENT_FIELDS = ['browser', 'os', 'viewport', 'pixel_ratio', 'language', 'color_scheme', 'page'];

    /**
     * A commit may be a single character, but an issue needs enough to act on.
     */
    public const int MIN_BODY_LENGTH = 3;

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * Only bug reports accept images, as screenshots.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>>
     */
    public function rules(): array
    {
        return [
            'command' => ['required', Rule::in(['/feature', '/bug'])],
            'body' => ['required', 'string', 'min:'.self::MIN_BODY_LENGTH, 'max:'.Commit::MAX_BODY_LENGTH],
            'environment' => ['nullable', 'array:'.implode(',', self::ENVIRONMENT_FIELDS)],
            'environment.*' => ['nullable', 'string', 'max:100'],
            ...collect(CreateCommitRequest::attachmentRules())->only(['images', 'images.*', 'image_descriptions', 'image_descriptions.*'])->all(),
            ...($this->input('command') !== '/bug' ? ['images' => ['prohibited'], 'image_descriptions' => ['prohibited']] : []),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        /** @var array<string, string|null> $environment */
        $environment = $this->array('environment');

        return array_filter($environment, fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }

    /**
     * @return list<string|null>
     */
    public function imageDescriptions(): array
    {
        /** @var list<string|null> $descriptions */
        $descriptions = $this->array('image_descriptions');

        return $descriptions;
    }
}
