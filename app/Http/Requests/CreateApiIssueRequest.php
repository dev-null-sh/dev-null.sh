<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

final class CreateApiIssueRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * The web's rules: text only, and the same environment fields, which a terminal fills as far as they apply.
     *
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return CreateIssueRequest::issueRules();
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return CreateIssueRequest::filledEnvironment($this);
    }
}
