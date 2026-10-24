<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShowUserRequest extends FormRequest
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
            'tab' => ['nullable', 'string', 'in:commits,replies,events'],
        ];
    }

    public function tab(): string
    {
        return in_array($this->input('tab'), ['replies', 'events'], true) ? $this->string('tab')->value() : 'commits';
    }
}
