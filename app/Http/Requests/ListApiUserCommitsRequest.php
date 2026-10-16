<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListApiUserCommitsRequest extends FormRequest
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
            'replies' => ['nullable', 'boolean'],
        ];
    }

    public function replies(): bool
    {
        return $this->boolean('replies');
    }
}
