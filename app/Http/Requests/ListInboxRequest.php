<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'archived' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function isArchived(): bool
    {
        return $this->boolean('archived');
    }

    public function term(): string
    {
        return $this->string('q')->trim()->ltrim('@')->lower()->value();
    }
}
