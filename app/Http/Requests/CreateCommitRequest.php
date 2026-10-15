<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\NotBlank;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, NotBlank|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', new NotBlank, 'min:3', 'max:72'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
