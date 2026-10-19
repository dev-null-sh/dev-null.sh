<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateIssueRequest extends FormRequest
{
    public const int MIN_BODY_LENGTH = 50;

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
            'body' => ['required', 'string', 'min:'.self::MIN_BODY_LENGTH, 'max:'.Commit::MAX_BODY_LENGTH],
        ];
    }
}
