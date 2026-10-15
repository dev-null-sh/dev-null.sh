<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveCommitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'commits' => ['required', 'array', 'max:100'],
            'commits.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
