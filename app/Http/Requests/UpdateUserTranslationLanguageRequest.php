<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CommitTranslations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserTranslationLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'translation_language' => ['present', 'nullable', 'string', Rule::in(resolve(CommitTranslations::class)->languages())],
        ];
    }

    public function language(): ?string
    {
        return $this->filled('translation_language') ? $this->string('translation_language')->value() : null;
    }
}
