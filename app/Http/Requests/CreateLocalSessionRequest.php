<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateLocalSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same shape as a GitHub handle: up to 39 letters, digits, and single hyphens, not at either end.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'handle' => ['required', 'string', 'max:39', 'regex:/^[a-z\d](?:[a-z\d]|-(?=[a-z\d]))*$/i'],
        ];
    }
}
