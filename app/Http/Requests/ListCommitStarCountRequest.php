<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListCommitStarCountRequest extends FormRequest
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
            'ids' => ['required', 'array', 'list', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('ids');

        return $ids;
    }
}
