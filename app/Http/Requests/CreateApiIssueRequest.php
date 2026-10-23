<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
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
     * An issue from the mobile app, sent as JSON, with the web's rules: its commands, a body long enough to act on, and
     * the same environment details the web sends about the browser and screen.
     *
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return [
            'command' => ['required', Rule::in(['/feature', '/bug'])],
            'body' => ['required', 'string', 'min:'.CreateIssueRequest::MIN_BODY_LENGTH, 'max:'.Commit::MAX_BODY_LENGTH],
            'environment' => ['nullable', 'array:'.implode(',', CreateIssueRequest::ENVIRONMENT_FIELDS)],
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
