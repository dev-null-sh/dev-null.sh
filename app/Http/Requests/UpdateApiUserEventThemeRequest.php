<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EventTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateApiUserEventThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'choice' => ['required', 'string', Rule::in(EventTheme::choices())],
        ];
    }

    public function choice(): string
    {
        return $this->string('choice')->value();
    }
}
