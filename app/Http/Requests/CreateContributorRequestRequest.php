<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContributorHelp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Web and signed API creation share normalization, commit limits, and GitHub URL validation. */
final class CreateContributorRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
        $url = $this->input('project_url');

        // The project link also belongs in the immutable commit, including in ordinary logs and threads.
        if (is_string($url) && mb_strlen($url) <= 255 && is_string($this->input('body')) && mb_trim($this->string('body')->value()) !== '') {
            $this->merge(['body' => $this->string('body')->value()."\n\n".mb_trim($url), 'project_url' => mb_trim($url)]);
        }
    }

    /** @return array<string, list<string|ValidationRule|Enum>> */
    public function rules(): array
    {
        return [
            'body' => CreateCommitRequest::bodyRules(),
            'project_url' => ['required', 'string', 'max:255', 'regex:~\Ahttps://github\.com/[a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38}/(?!\.{1,2}(?:/|$))[a-z\d_.-]+(?:/issues/[1-9]\d*)?/?\z~i'],
            'kind' => ['required', Rule::enum(ContributorHelp::class)],
        ];
    }
}
