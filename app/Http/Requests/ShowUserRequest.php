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
            'tab' => ['nullable', 'string', 'in:commits,replies,readme'],
        ];
    }

    public function tab(): string
    {
        return match ($this->input('tab')) {
            'replies' => 'replies',
            'readme' => 'readme',
            default => 'commits',
        };
    }
}
