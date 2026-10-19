<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateIssueRequest extends FormRequest
{
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
        ];
    }
}
