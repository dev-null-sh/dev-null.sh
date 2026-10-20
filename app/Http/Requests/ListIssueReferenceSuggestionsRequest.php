<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ListIssueReferenceSuggestionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'regex:/^\d{1,7}$/'],
        ];
    }

    public function digits(): string
    {
        return $this->string('q')->value();
    }
}
