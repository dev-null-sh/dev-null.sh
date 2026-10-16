<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListCommitSearchRequest extends FormRequest
{
    public const int DEFAULT_LIMIT = 20;

    private const int MAX_LIMIT = 100;

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
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    /**
     * The search dialog asks for a handful, the full list for a page.
     */
    public function limit(): int
    {
        return $this->filled('limit') ? $this->integer('limit') : self::DEFAULT_LIMIT;
    }
}
