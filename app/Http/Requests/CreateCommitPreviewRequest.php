<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

final class CreateCommitPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * @return array<string, array<int, string|ValidationRule|In>>
     */
    public function rules(): array
    {
        return [
            'body' => CreateCommitRequest::bodyRules(),
            ...CreateCommitRequest::guildRules($this),
        ];
    }

    public function passedValidation(): void
    {
        CreateCommitRequest::appendGuildHashtag($this);
    }
}
