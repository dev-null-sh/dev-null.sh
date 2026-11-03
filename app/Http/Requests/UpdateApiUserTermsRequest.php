<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The app says which version of the terms the person was shown, so an old build can't accept a version it never
 * showed them.
 */
final class UpdateApiUserTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', Rule::in([config('legal.terms_version')])],
        ];
    }
}
