<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserLaneRequest extends FormRequest
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
            'lane' => ['present', 'nullable', 'integer', 'between:0,5'],
        ];
    }

    public function lane(): ?int
    {
        return $this->filled('lane') ? $this->integer('lane') : null;
    }
}
