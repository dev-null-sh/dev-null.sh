<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateApiIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Blank lines are collapsed before the body is measured, as on the web.
     */
    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * An issue from the mobile app, sent as JSON, with the web's commands and its limits on a commit's body.
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
