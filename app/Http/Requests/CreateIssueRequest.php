<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateIssueRequest extends FormRequest
{
    public const array ENVIRONMENT_FIELDS = ['browser', 'os', 'viewport', 'pixel_ratio', 'language', 'color_scheme', 'page'];

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * Issues carry text only: images are not accepted.
     *
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return [
            'command' => ['required', Rule::in(['/feature', '/bug'])],
            'body' => CreateCommitRequest::BODY_RULES,
            'environment' => ['nullable', 'array:'.implode(',', self::ENVIRONMENT_FIELDS)],
            'environment.*' => ['nullable', 'string', 'max:100'],
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
}
