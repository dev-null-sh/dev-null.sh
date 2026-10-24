<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CommitTranslations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserKnownLanguagesRequest extends FormRequest
{
    public const int MAX_LANGUAGES = 10;

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
            'known_languages' => ['present', 'nullable', 'array', 'max:'.self::MAX_LANGUAGES],
            'known_languages.*' => ['string', 'distinct', Rule::in(resolve(CommitTranslations::class)->languages())],
        ];
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        /** @var list<string> $languages */
        $languages = $this->collect('known_languages')->values()->all();

        return $languages;
    }
}
