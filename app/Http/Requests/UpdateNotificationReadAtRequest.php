<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationReadAtRequest extends FormRequest
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
            'through' => ['required', 'date'],
        ];
    }

    public function through(): CarbonImmutable
    {
        $through = $this->date('through');
        assert($through !== null);

        return $through->toImmutable();
    }
}
