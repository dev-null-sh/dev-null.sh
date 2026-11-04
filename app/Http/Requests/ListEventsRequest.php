<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListEventsRequest extends FormRequest
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
            'tab' => ['nullable', 'string', 'in:upcoming,past'],
        ];
    }

    public function tab(): string
    {
        return $this->input('tab') === 'past' ? 'past' : 'upcoming';
    }
}
