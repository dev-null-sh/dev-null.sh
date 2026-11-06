<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ListCommitChangeRequest extends FormRequest
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
            'since' => ['required', 'integer', 'min:0'],
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

    /**
     * When the page last read what it shows, in milliseconds, never later than now.
     */
    public function since(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampMs(min($this->integer('since'), now()->getTimestampMs()));
    }
}
