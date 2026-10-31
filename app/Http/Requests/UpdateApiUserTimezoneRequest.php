<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateApiUserTimezoneRequest extends FormRequest
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
            'timezone' => ['present', 'nullable', 'string', 'timezone:all'],
            'show_local_time' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The timezone the user chose, or null to follow the device.
     */
    public function timezone(): ?string
    {
        return $this->filled('timezone') ? $this->string('timezone')->value() : null;
    }

    public function showLocalTime(): bool
    {
        return $this->boolean('show_local_time');
    }
}
