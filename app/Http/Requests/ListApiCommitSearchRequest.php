<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListApiCommitSearchRequest extends FormRequest
{
    private const int MAX_LIMIT = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A term shorter than three characters is never embedded, so the app is told rather than sent nothing.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:3', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    /**
     * The switcher asks for a handful, the results page for a page.
     */
    public function limit(): int
    {
        return $this->filled('limit') ? $this->integer('limit') : ListCommitSearchRequest::DEFAULT_LIMIT;
    }
}
